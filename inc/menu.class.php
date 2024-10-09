<?php
/*
 -------------------------------------------------------------------------
 MyDashboard plugin for GLPI
 Copyright (C) 2015 by the MyDashboard Development Team.
 -------------------------------------------------------------------------

 LICENSE

 This file is part of MyDashboard.

 MyDashboard is free software; you can redistribute it and/or modify
 it under the terms of the GNU General Public License as published by
 the Free Software Foundation; either version 2 of the License, or
 (at your option) any later version.

 MyDashboard is distributed in the hope that it will be useful,
 but WITHOUT ANY WARRANTY; without even the implied warranty of
 MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 GNU General Public License for more details.

 You should have received a copy of the GNU General Public License
 along with MyDashboard. If not, see <http://www.gnu.org/licenses/>.
 --------------------------------------------------------------------------
 */

/**
 * Class PluginMydashboardMenu
 */
class PluginMydashboardMenu extends CommonGLPI {
   const DASHBOARD_NAME = "myDashboard";
   /**
    * Will contain an array indexed with classnames, each element of this array<br>
    * will be an array containing widgetId s
    * @var array of array of string
    */
   private $widgets    = [];
   public  $widgetlist = [];
   /**
    * Will contain an array of strings with js function needed to add a widget
    * @var array of string
    */
   private $addfunction = [];
   /**
    * User id, most of the time it will correspond to currently connected user id,
    * but sometimes it will correspond to the DEFAULD_ID, for the default dashboard
    * @var int
    */
   private $users_id;
   /**
    * An array of string, each string is a widgetId of a widget that must be added on the mydashboard
    * @var array of string
    */
   private $dashboard = [];
   /**
    * An array of string indexed by classnames, each string is a statistic (time /mem)
    * @var array of string
    */
   private $stats = [];
   /**
    * A string to store infos, those infos are displayed in the top right corner of the mydashboard
    * @var string
    */
   //Unused
   //private $infos = "";
   public static  $ALL_VIEW                = -1;
   public static  $TICKET_REQUESTERVIEW    = 1;
   public static  $PROBLEM_VIEW            = 2;
   public static  $CHANGE_VIEW             = 3;
   public static  $GROUP_VIEW              = 4;
   public static  $MY_VIEW                 = 5;
   public static  $GLOBAL_VIEW             = 6;
   public static  $RSS_VIEW                = 7;
   public static  $PROJECT_VIEW            = 8;
   public static  $TICKET_TECHVIEW         = 9;
   private static $DEFAULT_ID              = 0;
   public static  $_PLUGIN_MYDASHBOARD_CFG = [];

   public $interface;

   static $rightname = "plugin_mydashboard";

   /**
    * @param int $nb
    *
    * @return translated
    */
   static function getTypeName($nb = 0) {
      return __('My Dashboard', 'mydashboard');
   }


   function defineTabs($options = []) {

      $ong = [];
      $this->addStandardTab(__CLASS__, $ong, $options);

      return $ong;
   }

   function getTabNameForItem(CommonGLPI $item, $withtemplate = 0) {

      if ($item->getType() == __CLASS__) {
         $tabs[1] = __('My view', 'mydashboard');
         $tabs[2] = __('ITSM-NG admin grid', 'mydashboard');
         //         $tabs[3] = __('Inventory admin grid', 'mydashboard');
         //         $tabs[4] = __('Helpdesk supervisor grid', 'mydashboard');
         //         $tabs[5] = __('Incident supervisor grid', 'mydashboard');
         //         $tabs[6] = __('Request supervisor grid', 'mydashboard');
         //         $tabs[7] = __('Helpdesk technician grid', 'mydashboard');
         return $tabs;
      }
      return '';
   }

   static function displayTabContentForItem(CommonGLPI $item, $tabnum = 1, $withtemplate = 0) {

      $profile         = (isset($_SESSION['glpiactiveprofile']['id'])) ? $_SESSION['glpiactiveprofile']['id'] : -1;
      $predefined_grid = 0;

      if (isset($_POST["profiles_id"])) {
         $profile = $_POST["profiles_id"];
      }
      if (isset($_POST["predefined_grid"])) {
         $predefined_grid = $_POST["predefined_grid"];
      }
      $self = new self();
      $self->initDBWidgets();

      if ($item->getType() == __CLASS__) {
         switch ($tabnum) {
            case 1 :
               $self->loadDashboard($profile, $predefined_grid);
               break;
            case 2 :
               $self->loadDashboard($profile, 1);
               break;
            default :
               break;
         }
      }
      return true;
   }

   /**
    * PluginMydashboardMenu constructor.
    *
    * @param bool $show_all
    */
   function __construct($show_all = false) {
      $this->initConfig($show_all);
   }

   /**
    * Initialize the mydashboard config
    *
    * @param $show_all
    */
   private function initConfig($show_all) {

      //Configuration set by Administrator (via Configuration->Plugins ...)
      $config = new PluginMydashboardConfig();
      $config->getConfig();

      self::$_PLUGIN_MYDASHBOARD_CFG['enable_fullscreen']     = $config->fields['enable_fullscreen']; // 0 (FALSE) or 1 (TRUE), enable the possibility to display the mydashboard in fullscreen
      self::$_PLUGIN_MYDASHBOARD_CFG['display_menu']          = $config->fields['display_menu']; // Display the right menu slider
      self::$_PLUGIN_MYDASHBOARD_CFG['display_plugin_widget'] = $config->fields['display_plugin_widget']; // Display widgets of plugins
      self::$_PLUGIN_MYDASHBOARD_CFG['replace_central']       = $config->fields['replace_central']; // Replace central interface

      unset($config);

      //Configuration set by User (via My Preferences -> Dashboard tab)
      //General Settings
      $preference = new PluginMydashboardPreference();
      if (!$preference->getFromDB(Session::getLoginUserID())) {
         $preference->initPreferences(Session::getLoginUserID());
      }
      $preference->getFromDB(Session::getLoginUserID());

      self::$_PLUGIN_MYDASHBOARD_CFG['automatic_refresh']       = $preference->fields['automatic_refresh'];  //Wether or not refreshable widget will be automatically refreshed by automaticRefreshDelay minutes
      self::$_PLUGIN_MYDASHBOARD_CFG['automatic_refresh_delay'] = $preference->fields['automatic_refresh_delay']; //In minutes
      self::$_PLUGIN_MYDASHBOARD_CFG['replace_central']         = $preference->fields['replace_central']; // Replace central interface

   }

   /**
    * @return array
    */
   static function getMenuContent() {
      $plugin_page = "/plugins/mydashboard/front/menu.php";
      $menu        = [];
      //Menu entry in tools
      $menu['title']           = self::getTypeName();
      $menu['page']            = $plugin_page;
      $menu['links']['search'] = $plugin_page;
      if (Session::haveRightsOr("plugin_mydashboard_config", [CREATE, UPDATE])
          || Session::haveRight("config", UPDATE)) {
         //Entry icon in breadcrumb
         $menu['links']['config'] = PluginMydashboardConfig::getFormURL(false);
      }

      $menu['options']['pluginmydashboardstockwidget'] = [
         'title' => PluginMydashboardStockWidget::getTypeName(2),
         'page'  => PluginMydashboardStockWidget::getSearchURL(false),
         'links' => [
            'search' => PluginMydashboardStockWidget::getSearchURL(false),
            'add'    => PluginMydashboardStockWidget::getFormURL(false)
         ]
      ];

      $menu['icon'] = self::getIcon();

      return $menu;
   }

   /**
    * @return string
    */
   static function getIcon() {
      return "fas fa-tachometer-alt";
   }

   /**
    * Show dashboard
    *
    * @param int $users_id
    * @param int $active_profile
    *
    * @return FALSE if the user haven't the right to see Dashboard
    * @internal param type $user_id
    */
   public function showMenu($rand, $users_id = -1, $active_profile = -1, $predefined_grid = 0) {

      //We check the wanted interface (this param is later transmitted to PluginMydashboardUserWidget to get the dashboard for the user in this interface)
      $this->interface = (Session::getCurrentInterface() == 'central') ? 1 : 0;

      // validation des droits
      if (!Session::haveRightsOr("plugin_mydashboard", [CREATE, READ])) {
         return false;
      }
      // checking if no users_id is specified
      $this->users_id = Session::getLoginUserID();
      if ($users_id != -1) {
         $this->users_id = $users_id;
      }

      //Now the mydashboard
      $this->showDashboard($rand, $active_profile, $predefined_grid);

   }


   /**
    * Dropdown profiles which have rights under the active one
    *
    * @param $options array of possible options:
    *    - name : string / name of the select (default is profiles_id)
    *    - value : integer / preselected value (default 0)
    *
    **/
   static function dropdownProfiles($options = []) {
      global $DB;

      $p['name']  = 'profiles_id';
      $p['value'] = '';
      $p['rand']  = mt_rand();
      $profiles   = [];
      if (is_array($options) && count($options)) {
         foreach ($options as $key => $val) {
            $p[$key] = $val;
         }
      }
      $iterator = $DB->request(
         ['SELECT'    => [
            'glpi_profiles.name',
            'glpi_profiles.id'
         ],
          'FROM'      => Profile::getTable(),
          'LEFT JOIN' => [
             'glpi_profilerights' => [
                'FKEY' => [
                   'glpi_profilerights' => 'profiles_id',
                   'glpi_profiles'      => 'id'
                ]
             ]
          ],
          'WHERE'     => [Profile::getUnderActiveProfileRestrictCriteria(),
                          'glpi_profilerights.name'   => 'plugin_mydashboard',
                          'glpi_profilerights.rights' => ['>', 0],
          ],
          'ORDER'     => 'glpi_profilerights.name'
         ]);

      //New rule -> get the next free ranking
      while ($data = $iterator->next()) {
         $profiles[$data['id']] = $data['name'];
      }

      Dropdown::showFromArray($p['name'], $profiles,
                              ['value'               => $p['value'],
                               'rand'                => $p['rand'],
                               'display_emptychoice' => true,
                               'on_change'           => 'this.form.submit()']);
   }

   /**
    * This method shows the widget list (in the left part) AND the mydashboard
    *
    * @param int $selected_profile
    */
   private function showDashboard($rand, $selected_profile = -1, $predefined_grid = 0) {

      //If we want to display the widget list menu, we have to 'echo' it, else we also need to call it because it initialises $this->widgets (link between classnames and widgetId s)
      //      $_SESSION['plugin_mydashboard_editmode'] = false;
      //      $edit = PluginMydashboardPreference::checkEditMode(Session::getLoginUserID());
      //      if ($edit > 0) {
      //         echo $this->getWidgetsList($selected_profile, $edit);
      //      }

      //Now we have a widget list menu, but, it does nothing, we have to bind
      //list item click with the adding on the mydashboard, and we need to display
      //this div contains the header and the content (basically the ul used by sDashboard)

      echo "<div class='plugin_mydashboard_dashboard' >";//(div.plugin_mydashboard_dashboard)

      //This first div is the header of the mydashboard, basically it display a name, informations and a button to toggle full screen
      echo "<div class='plugin_mydashboard_header'>";//(div.plugin_mydashboard_header)
      echo "</div>";//end(div.plugin_mydashboard_header)
      //Now the content
      //      echo "<div class='plugin_mydashboard_content'>";//(div.plugin_mydashboard_content)
      //
      //      echo "</div>";//end(div.plugin_mydashboard_content)
      echo "</div>";//end(div.plugin_mydashboard_dashboard)

      //      //Automatic refreshing of the widgets (that wants to be refreshed -> see PluginMydashboardModule::toggleRefresh() )
      if (self::$_PLUGIN_MYDASHBOARD_CFG['automatic_refresh']) {
         //We need some javascript, here are scripts (script which have to be dynamically called)
         $refreshIntervalMs = 60000 * self::$_PLUGIN_MYDASHBOARD_CFG['automatic_refresh_delay'];
         //this js function call itself every $refreshIntervalMs ms, each execution result in the refreshing of all refreshable widgets

         echo Html::scriptBlock('
            function automaticRefreshAll(delay) {
                 setInterval(function () {
                     mydashboard.refreshAll();
                 }, delay);
             }
            function refreshAll() {
                 $(\'.refresh-icon\').trigger(\'click\');
             };');

         echo Html::scriptBlock('
               automaticRefreshAll(' . $refreshIntervalMs . ');
         ');

      }
   }

   function displayEditMode($rand, $edit = 0, $selected_profile = -1, $predefined_grid = 0) {

      $drag = PluginMydashboardPreference::checkDragMode(Session::getLoginUserID());

      echo $this->getscripts();

      if ($edit > 0) {

         echo "<div class='center'>";

         echo "<form method='post'
                     action='" . $this->getSearchURL() . "' onsubmit='return true;'>";

         echo "<table class='tab_cadre_fixe' width='100%'>";


         echo "<tr><th>";
         echo "&nbsp;" . __('Availables widgets', 'mydashboard');
         echo "</th>";
         echo "</tr>";
         echo "<tr>";
         echo "<td class='left' style='padding: 0px;'>";
         echo $this->getWidgetsList($selected_profile, $edit);
         echo "</th>";
         echo "</tr>";

         echo "<tr><th>";
         echo __('Edit mode', 'mydashboard');
         if ($edit == 2) {
            echo " (" . __('Global', 'mydashboard') . ")";
         }
         echo "</th>";
         echo "</tr>";

         if (Session::haveRight("plugin_mydashboard_config", CREATE) && $edit == 2) {
            echo "<tr>";
            echo "<td class='center'>";
            echo "<span class='editmode_test'>" . __('Profile') . "</span>&nbsp;";
            echo "<br><br>";
            self::dropdownProfiles(['value' => $selected_profile]);
            echo "</td>";
            echo "<tr>";
         } else {
            echo Html::hidden("profiles_id", ['value' => $_SESSION['glpiactiveprofile']['id']]);
         }


         echo "<tr>";
         echo "<td class='center' style='border: 0;'>";

         echo "<span class='editmode_test'>" . __('Load a predefined grid', 'mydashboard') . "</span>&nbsp;";
         echo "<span class='sr-only'>" . __('Load a predefined grid', 'mydashboard') . "</span>";
         echo "<br><br>";
         $elements = PluginMydashboardDashboard::getPredefinedDashboardName();
         Dropdown::showFromArray("predefined_grid", $elements, [
            'value'               => $predefined_grid,
            'width'               => '170px',
            'display_emptychoice' => true,
            'on_change'           => 'this.form.submit()']);

         echo "<br><br>";

         if (!Session::haveRight("plugin_mydashboard_config", CREATE) && $edit == 2) {
            $edit = 1;
         }

         echo "<a id='load-widgets' class='btn btn-info btn-sm btn-mydashboard'>";
         echo "<i class='fas fa-1x fa-spinner pointer btn-mydashboard' title='" . __('Load widgets', 'mydashboard') . "'
                           data-hasqtip='0' aria-hidden='true'></i>";
         echo "<br>" . __('Load widgets', 'mydashboard');
         echo "</a>";
         echo "<br><br>";

         if ($edit == 1) {
            echo "<a id='save-grid' class='btn btn-success btn-sm btn-mydashboard'>";
            echo "<i class='fas fa-1x fa-save pointer btn-mydashboard' title='" . __('Save grid', 'mydashboard') . "'
                           data-hasqtip='0' aria-hidden='true'></i>";
            echo "<br>" . __('Save grid', 'mydashboard');
            echo "</a>";
            echo "<br><br>";
         }
         if (Session::haveRight("plugin_mydashboard_config", CREATE) && $edit == 2) {
            echo "<a id='save-default-grid' class='btn btn-success btn-sm btn-mydashboard'>";
            echo "<i class='fas fa-1x fa-hdd pointer btn-mydashboard' title='" . __('Save default grid', 'mydashboard') . "'
                           data-hasqtip='0' aria-hidden='true'></i>";
            echo "<br>" . __('Save default grid', 'mydashboard');
            echo "</a>";
            echo "<br><br>";
         }

         echo "<a id='clear-grid' class='btn btn-danger btn-sm btn-mydashboard'>";
         echo "<i class='fas fa-1x fa-window-restore pointer btn-mydashboard' title='" . __('Clear grid', 'mydashboard') . "'
                           data-hasqtip='0' aria-hidden='true'></i>";
         echo "<br>" . __('Clear grid', 'mydashboard');
         echo "</a>";
         echo "<br><br>";

         if ($drag < 1 && Session::haveRight("plugin_mydashboard_edit", 6)) {
            echo "<a id='drag-grid' class='btn btn-danger btn-sm btn-mydashboard'>";
            echo "<i class='fas fa-1x fa-lock pointer btn-mydashboard' title='" . __('Permit drag / resize widgets', 'mydashboard') . "'
                           data-hasqtip='0' aria-hidden='true'></i>";
            echo "<br>" . __('Permit drag / resize widgets', 'mydashboard');
            echo "</a>";
            echo "<br><br>";

         }
         if ($drag > 0 && Session::haveRight("plugin_mydashboard_edit", 6)) {

            echo "<a id='undrag-grid' class='btn btn-success btn-sm btn-mydashboard'>";
            echo "<i class='fas fa-1x fa-unlock-alt pointer btn-mydashboard' title='" . __('Block drag / resize widgets', 'mydashboard') . "'
                           data-hasqtip='0' aria-hidden='true'></i>";
            echo "<br>" . __('Block drag / resize widgets', 'mydashboard');
            echo "</a>";
            echo "<br><br>";
         }

         $this->interface = (Session::getCurrentInterface() == 'central') ? 1 : 0;
         if (self::$_PLUGIN_MYDASHBOARD_CFG['enable_fullscreen']
             && $edit < 1
             && $this->interface == 1) {
            echo "<a id='header_fullscreen' class='btn btn-info btn-sm btn-mydashboard'>";
            echo "<i class='fas fa-1x fa-arrows-alt pointer btn-mydashboard' title='" . __("Fullscreen", "mydashboard") . "'
                           data-hasqtip='0' aria-hidden='true'></i>";
            echo "<br>" . __("Fullscreen", "mydashboard");
            echo "</a>";
            echo "<br><br>";
         }

         echo "<a id='close-edit' class='btn btn-success btn-sm btn-mydashboard' style='color: red;'>";
         echo "<i class='fas fa-1x fa-times-circle pointer btn-mydashboard' title='" . __("Close edit mode", "mydashboard") . "'
                           data-hasqtip='0' aria-hidden='true'></i>";
         echo "<br>" . __("Close edit mode", "mydashboard");
         echo "</a>";

         echo "</td>";
         echo "</tr>";

         echo "</table>";
         Html::closeForm();
         echo "</div>";


         echo "<div class='alert alert-success' id='success-alert'>
                <strong>" . __('Success', 'mydashboard') . "</strong> -
                " . __('The widget was added to dashboard. Save the dashboard.', 'mydashboard') . "
            </div>";
         echo Html::scriptBlock('
               $("#success-alert").hide();
         ');

         echo "<div class='bt-alert bt-alert-error' id='error-alert'>
                <strong>" . __('Error', 'mydashboard') . "</strong>
                " . __('Please reload your page.', 'mydashboard') . "
            </div>";
         echo Html::scriptBlock('
               $("#error-alert").hide();
         ');

      } else {
         echo "<div class='center'>";
         echo "<br>";

         if ($drag > 0 && Session::haveRight("plugin_mydashboard_edit", 6)) {

            echo "<a id='save-grid' class='btn btn-success btn-sm btn-mydashboard'>";
            echo "<i class='fas fa-1x fa-save pointer btn-mydashboard' title='" . __('Save grid', 'mydashboard') . "'
                           data-hasqtip='0' aria-hidden='true'></i>";
            echo "<br>" . __('Save grid', 'mydashboard');
            echo "</a>";
            echo "<br><br>";

            echo "<a id='undrag-grid' class='btn btn-success btn-sm btn-mydashboard'>";
            echo "<i class='fas fa-1x fa-unlock-alt pointer btn-mydashboard' title='" . __('Block drag / resize widgets', 'mydashboard') . "'
                           data-hasqtip='0' aria-hidden='true'></i>";
            echo "<br>" . __('Block drag / resize widgets', 'mydashboard');
            echo "</a>";
            echo "<br><br>";

         }

         if ($drag < 1 && Session::haveRight("plugin_mydashboard_edit", 6)) {

            echo "<a id='drag-grid' class='btn btn-danger btn-sm btn-mydashboard'>";
            echo "<i class='fas fa-1x fa-lock pointer btn-mydashboard' title='" . __('Permit drag / resize widgets', 'mydashboard') . "'
                           data-hasqtip='0' aria-hidden='true'></i>";
            echo "<br>" . __('Permit drag / resize widgets', 'mydashboard');
            echo "</a>";
            echo "<br><br>";
         }

         if (Session::haveRight("plugin_mydashboard_edit", 6)) {

            echo "<a id='edit-grid' class='btn btn-danger btn-sm btn-mydashboard'>";
            echo "<i class='fas fa-1x fa-edit pointer btn-mydashboard' title='" . __('Switch to edit mode', 'mydashboard') . "'
                           data-hasqtip='0' aria-hidden='true'></i>";
            echo "<br>" . __('Switch to edit mode', 'mydashboard');
            echo "</a>";
            echo "<br><br>";
         }

         if (Session::haveRight("plugin_mydashboard_config", CREATE)) {

            echo "<a id='edit-default-grid' class='btn btn-danger btn-sm btn-mydashboard'>";
            echo "<i class='fas fa-1x fa-cogs pointer btn-mydashboard' title='" . __('Custom and save default grid', 'mydashboard') . "'
                           data-hasqtip='0' aria-hidden='true'></i>";
            echo "<br>" . __('Custom and save default grid', 'mydashboard');
            echo "</a>";
            echo "<br><br>";
         }

         $this->interface = (Session::getCurrentInterface() == 'central') ? 1 : 0;
         if (self::$_PLUGIN_MYDASHBOARD_CFG['enable_fullscreen']
             && $edit < 1
             && $this->interface == 1) {

            echo "<a id='header_fullscreen' class='btn btn-info btn-sm btn-mydashboard'>";
            echo "<i class='fas fa-1x fa-arrows-alt pointer btn-mydashboard' title='" . __("Fullscreen", "mydashboard") . "'
                           data-hasqtip='0' aria-hidden='true'></i>";
            echo "<br>" . __("Fullscreen", "mydashboard");
            echo "</a>";
         }
      }
      echo "<div id='ajax_loader' class=\"ajax_loader hidden\">";
      echo "</div>";
   }

   /**
    * Get the HTML view of the widget list, the lateral menu
    *
    * @param     $profile
    * @param int $edit
    *
    * @return string, HTML
    */
   public function getWidgetsList($profile, $edit = 0) {

      $list             = new PluginMydashboardWidgetlist();
      $this->widgetlist = $list->getList(true, $profile);

      $grid = [];
      $used = [];

      $dashboard = new PluginMydashboardDashboard();

      if ($edit == 2) {
         $options = ["users_id"    => 0,
                     "profiles_id" => $profile];
         $id      = PluginMydashboardDashboard::checkIfPreferenceExists($options);
         if ($dashboard->getFromDB($id)) {
            $grid = stripslashes($dashboard->fields['grid']);
         }
      }
      if ($edit == 1) {
         $option_users = ["users_id"    => Session::getLoginUserID(),
                          "profiles_id" => $profile];
         $id           = PluginMydashboardDashboard::checkIfPreferenceExists($option_users);
         if ($dashboard->getFromDB($id)) {
            $grid = stripslashes($dashboard->fields['grid']);
         }
      }

      if (!empty($grid) && ($datagrid = json_decode($grid, true)) == !null) {
         foreach ($datagrid as $k => $v) {
            $used[] = $v["id"];
         }
      }
      $layout = $_SESSION['glpilayout'];
      $wl     = "<script>

            $(document).ready(function () {
                var layout = '$layout';
                //===================Start:Showing Menu=====================================
                //Showing the menu on click
                $('.plugin_mydashboard_add_button').on('click', function (e) {
//                    $('.plugin_mydashboard_menuDashboard').width(400);
                    $('.plugin_mydashboard_menuDashboard').show();
                });
                //Hiding the menu when clicking outside the menu
                var menu = false;
                $(\"#success-alert\").hide();
                $('.plugin_mydashboard_add_button,.plugin_mydashboard_menuDashboard').click(function (e) {
                    menu = true;
                });
                $(document).click(function () {
                  if (!menu) {
                      $('.plugin_mydashboard_menuDashboard').hide();
                  } else {
                      menu = false;
                  }
                });

                //===================Stop:Showing Menu=====================================
                //===================Start:AccordionEffect=================================
                //Now the accordion effect w/o jQuery Accordion (wasn't really customizable, and css from other plugin can override dashboard one)
                //at the beginning every lists of widgets are folded
                $('.plugin_mydashboard_menuDashboardListContainer,.plugin_mydashboard_menuDashboardList2').slideUp('fast');

                //binding when user wants to unfold/fold a list of widget
                $('.plugin_mydashboard_menuDashboardListTitle1').click(function () {
                    var isOpened = $(this).hasClass('plugin_mydashboard_menuDashboardListTitle1Opened');
                    $('.plugin_mydashboard_menuDashboardListTitle1').removeClass(\"plugin_mydashboard_menuDashboardListTitle1Opened\");
                  if (!isOpened) {
                     $(this).addClass(\"plugin_mydashboard_menuDashboardListTitle1Opened\");
                  }
                    $('.plugin_mydashboard_menuDashboardListTitle1').not(this).next(\"table\").slideUp('fast');
                    $(this).next(\"table\").slideToggle('fast');
                });

                //This part is about lists of lists of widgets (when there are much widgets)
                //Every list of list are closed at the beginning
               //   $('.plugin_mydashboard_menuDashboardList2').slideUp('fast');
                //Binding when user want to unfold/fold a list of widget
                $('.plugin_mydashboard_menuDashboardListTitle2').click(function () {
                    var isOpened = $(this).hasClass('plugin_mydashboard_menuDashboardListTitle1Opened');
                    $('.plugin_mydashboard_menuDashboardListTitle2').removeClass(\"plugin_mydashboard_menuDashboardListTitle1Opened\");
                  if (!isOpened) {
                     $(this).addClass(\"plugin_mydashboard_menuDashboardListTitle1Opened\");
                  }
                    $('.plugin_mydashboard_menuDashboardListTitle2').not(this).next(\"table\").slideUp('fast');
                    $(this).next(\"table\").slideToggle('fast');
                });
                //===================Stop:AccordionEffect=================================
                //===================Start:ListItem click=================================
                //handling click on all listitem (button to add a specific widget), -> getWidget with data stored in a custom attribute (html5 prefixed as data-*)
                //XACA
                $('.plugin_mydashboard_menuDashboardListItem').click(function () {
                    var dashboardId = $(this).parents('.plugin_mydashboard_menuDashboard').attr('data-dashboardid');
                    var widgetId = $(this).attr('data-widgetid');
                    var classname = $(this).attr('data-classname');
                    var attrview = $(this).attr('data-view');
                    var view = \"\";
                  if (typeof attrview != \"undefined\") {
                     view = \"<span class='plugin_mydashboard_discret'>&nbsp;-&nbsp;\" + attrview + \"</span>\";
                  }
                  if (addNewWidget(widgetId) === true) {
//                      $(\"#success-alert\").fadeTo(2000, 500).slideUp(500, function () {
//                          $(\"#success-alert\").slideUp(500);
//                      });
                      $('.plugin_mydashboard_menuDashboard').hide();
                  } else {
                      //error
//                      $(\"#error-alert\").fadeTo(2000, 500).slideUp(500, function () {
//                          $(\"#error-alert\").slideUp(500);
//                      });
                  }
                });
            });

        </script>";
      //menuMyDashboard is the non moving part (it's just it width that changes)
      //      $wl .= "<table class='plugin_mydashboard_menuDashboard' "
      //             . " data-dashboardid='" . self::DASHBOARD_NAME . "'"
      //             . ">";//(div.plugin_mydashboard_menuDashboard)
      //      menuSlider is the moving part (jQuery changing the css property margin-right)
      //      $wl .= "<table class='plugin_mydashboard_menuSlider'>";  //(div.plugin_mydashboard_menuSlider)
      //        $wl .= "<div class='plugin_mydashboard_menuSliderHeader'>".$this->getTypeName()."</div>";
      //menuSliderContent contains the lists of widgets
      //      $wl .= "<table class='plugin_mydashboard_menuSliderContent'>"; //(div.plugin_mydashboard_menuSliderContent)

      $empty       = false;
      $widgetslist = PluginMydashboardWidget::getWidgetList();
      $gslist      = [];
      foreach ($widgetslist as $gs => $widgetclasses) {
         $gslist[$widgetclasses['id']] = $gs;
      }

      //1) we 'display' GLPI core widgets in the list
      if ($this->getWidgetsListFromGLPICore($used, $wl, $gslist)) {
         $empty = true;
      }
      //2) we 'display' Plugin widgets
      if (self::$_PLUGIN_MYDASHBOARD_CFG['display_plugin_widget']) {
         if ($this->getWidgetsListFromPlugins($used, $wl, $gslist)) {
            $empty = ($empty) ? $empty : false;
         } else {
            $empty = false;
         }
      }

      if ($empty) {
         $wl .= __('No widgets available', 'mydashboard');
      }
      //-------------------------------------------------------
      //      $wl .= "</table>"; //end(div.plugin_mydashboard_menuSliderContent)

      //      $wl .= "</table>"; //end(div.plugin_mydashboard_menuSlider)
      //      $wl .= "</table>"; //end(div.plugin_mydashboard_menuDashboard)

      return $wl;
   }


   /**
    * @return string
    */
   public function getscripts() {

      $wl = "<script>

            $(document).ready(function () {

                 $('#load-widgets').click(function () {
                    launchloadWidgets();
                });
                 $('#clear-grid').click(function () {
                    launchClearGrid();
                });
                 $('#header_fullscreen').click(function () {
                    launchFullscreen();
                });
                 $('#edit-grid').click(function () {
                    launchEditMode();
                });
                 $('#edit-default-grid').click(function () {
                    launchEditDefaultMode();
                });
                 $('#close-edit').click(function () {
                    launchCloseEditMode();
                });
                 $('#save-grid').click(function () {
                    launchSaveGrid();
                });
                 $('#save-default-grid').click(function () {
                    launchSaveDefaultGrid();
                });
                 $('#drag-grid').click(function () {
                    launchDragGrid();
                });
                 $('#undrag-grid').click(function () {
                    launchUndragGrid();
                });
            });

        </script>";

      return $wl;
   }

   /**
    * Initialization of widgets at installation
    */
   static function installWidgets() {

      $list       = new PluginMydashboardWidgetlist();
      $widgetlist = $list->getList(false);

      $widgetDB = new PluginMydashboardWidget();

      foreach ($widgetlist as $widgetclasses) {
         foreach ($widgetclasses as $widgetclass => $widgets) {
            foreach ($widgets as $widgetview => $widgetlist) {
               if (is_array($widgetlist)) {
                  foreach ($widgetlist as $widgetId => $widgetTitle) {
                     if (is_numeric($widgetId)) {
                        $widgetId = $widgetTitle;
                     }
                     $widgetDB->saveWidget($widgetId);
                  }
               } else {
                  if (is_numeric($widgetview)) {
                     $widgetview = $widgetlist;
                  }
                  $widgetDB->saveWidget($widgetview);
               }
            }
         }
      }
   }

   /**
    * Stores every widgets in Database (see PluginMydashboardWidget)
    */
   private function initDBWidgets() {
      $widgetDB    = new PluginMydashboardWidget();
      $dbu         = new DbUtils();
      $widgetsinDB = $dbu->getAllDataFromTable(PluginMydashboardWidget::getTable());

      $widgetsnames = [];
      foreach ($widgetsinDB as $widget) {
         $widgetsnames[$widget['name']] = $widget['id'];
      }

      foreach ($this->widgets as $classname => $classwidgets) {
         foreach ($classwidgets as $widgetId => $view) {
            if (!isset($widgetsnames[$widgetId])) {
               $widgetDB->saveWidget($widgetId);
            }
         }
      }
   }


   /**
    * Get the HTML list of the GLPI core widgets available
    *
    * @param array  $used
    * @param string $html the HTML list
    *
    * @return bool|string is empty ?
    */
   public function getWidgetsListFromGLPICore($used = [], &$html = "", $gslist = []) {

      $wl = "<h5 class='plugin_mydashboard_menuDashboardListTitle1'>GLPI</h5>";
      $wl .= "<table style='width: 100%;' class='plugin_mydashboard_menuDashboardListContainer'>";

      //GLPI core classes doesn't display the same thing in each view, we need to provide all views available
      $views = [self::$TICKET_REQUESTERVIEW,
                self::$TICKET_TECHVIEW,
                self::$PROBLEM_VIEW,
                self::$CHANGE_VIEW,
                self::$PROJECT_VIEW,
                self::$GROUP_VIEW,
                self::$MY_VIEW,
                self::$GLOBAL_VIEW,
                self::$RSS_VIEW];
      //To ease navigation we display the name of the view
      $viewsNames = $this->getViewNames();

      $viewContent = [];
      foreach ($views as $view) {
         $viewContent[$view] = "";
      }

      if (!isset($this->widgetlist['GLPI'])) {
         return '';
      }
      $widgetclasses = $this->widgetlist['GLPI'];

      foreach ($widgetclasses as $widgetclass => $widgets) {
         foreach ($widgets as $widgetview => $widgetlist) {
            foreach ($widgetlist as $widgetId => $widgetTitle) {

               if (!is_array($widgetTitle)) {
                  if (is_numeric($widgetId)) {
                     $widgetId = $widgetTitle;
                  }
                  $this->widgets[$widgetclass][$widgetId] = $viewsNames[$widgetview];
                  $gsid                                   = $gslist[$widgetId];
                  if (!in_array($gsid, $used)) {
                     $viewContent[$widgetview] .= "<tr><td class='plugin_mydashboard_menuDashboardListItem'"
                                                  . " data-widgetid='" . $gsid . "'"
                                                  . " data-classname='" . $widgetclass . "'"
                                                  . " data-view='" . $viewsNames[$widgetview] . "'>";
                     $viewContent[$widgetview] .= $widgetTitle;
                     if ($_SESSION['glpi_use_mode'] == Session::DEBUG_MODE) {
                        $viewContent[$widgetview] .= " (" . $gsid . ")";
                     }
                     $viewContent[$widgetview] .= "</td></tr>";
                  }
               } else {
                  if (isset($widgetTitle['title'])) {
                     if (is_numeric($widgetId)) {
                        $widgetId = $widgetTitle;
                     }
                     $this->widgets[$widgetclass][$widgetId] = $viewsNames[$widgetview];
                     $gsid                                   = $gslist[$widgetId];
                     if (!in_array($gsid, $used)) {

                        $viewContent[$widgetview] .= "<tr><td class='media plugin_mydashboard_menuDashboardListItem'"
                                                     . " data-widgetid='" . $gsid . "'"
                                                     . " data-classname='" . $widgetclass . "'"
                                                     . " data-view='" . $viewsNames[$widgetview] . "'>";
                        $icon                     = $widgetTitle['icon'] ?? "";
                        if (!empty($icon)) {
                           $viewContent[$widgetview] .= "<div class='media-left'><i class='$icon fa-3x'></i></div>";
                        }
                        $viewContent[$widgetview] .= "<div class='media-body' style='margin: 10px;'>";
                        $viewContent[$widgetview] .= $widgetTitle['title'];
                        if ($_SESSION['glpi_use_mode'] == Session::DEBUG_MODE) {
                           $viewContent[$widgetview] .= " (" . $gsid . ")";
                        }
                        $comment = $widgetTitle['comment'] ?? "";
                        if (!empty($comment)) {
                           $viewContent[$widgetview] .= "<br><span class='widget-comment'>$comment</span>";
                        }
                        $viewContent[$widgetview] .= "</div></td></tr>";
                     }
                  }
               }
            }
         }
      }
      $is_empty = true;
      //Now we display each group (view) as a list
      foreach ($viewContent as $view => $vContent) {
         if ($vContent != '') {
            $wl .= "<tr><td class='media plugin_mydashboard_menuDashboardList'>";
//            $wl .= "<span class='media'>";
            $wl .= "<span class='media-left'>";
            $wl .= "<i class='far fa-folder fa-2x'></i>";
            $wl .= "</span>";
//            $wl .= "<span class=''>";
            $wl .= "<h6 class='media-body plugin_mydashboard_menuDashboardListTitle2'>";
            $wl .= $viewsNames[$view];
            $wl .= "</h6>";
//            $wl .= "</span>";
//            $wl .= "</span>";
            $wl .= "<table style='width: 100%;' class='plugin_mydashboard_menuDashboardList2'>";
            if (!empty($vContent)) {
               $wl .= $vContent;
            }
            $wl       .= "</table></td></tr>";
            $is_empty = false;
         }
      }

      $wl .= "</table>";
      if ($is_empty) {
         return true;
      } else {
         $html .= $wl;
         return false;
      }
   }

   /**
    * Get the HTML list of the plugin widgets available
    *
    * @param array $used
    *
    * @return string|boolean
    * @global type $PLUGIN_HOOKS , that's where you have to declare your classes that defines widgets, in
    *    $PLUGIN_HOOKS['mydashboard'][YourPluginName]
    */
   public function getWidgetsListFromPlugins($used = [], &$html = "", $gslist = []) {
      $plugin_names                = $this->getPluginsNames();
      $plugin_names["mydashboard"] = __('My Dashboard', 'mydashboard');
      $plugins_is_empty            = true;
      foreach ($this->widgetlist as $plugin => $widgetclasses) {
         if ($plugin == "GLPI") {
            continue;
         }
         $is_empty = true;

         $tmp = "<h5 class='plugin_mydashboard_menuDashboardListTitle1'>" . ucfirst($plugin_names[$plugin]) . "</h5>";
         //Every widgets of a plugin are in an accordion (handled by dashboard not the jquery one)
         $tmp .= "<table style='width: 100%;' class='plugin_mydashboard_menuDashboardListContainer'>";
         //         $tmp .= "<tr>";

         foreach ($widgetclasses as $widgetclass => $widgetlist) {
            $res = $this->getWidgetsListFromWidgetsArray($widgetlist, $widgetclass, 2, $used, $gslist);
            if (!empty($widgetlist) && $res != '') {
               $tmp      .= $res;
               $is_empty = false;
            }
         }

         $tmp .= "</table>";

         //If there is now widgets available from this plugins we don't display menu entry
         if (!$is_empty) {
            $html .= $tmp;
            if ($plugins_is_empty) {
               $plugins_is_empty = false;
            }
         }
      }
      return $plugins_is_empty;
   }


   /**
    *
    * @param type  $widgetsarray , an arry of widgets (or array of array ... of widgets)
    * @param type  $classname , name of the class containing the widget
    * @param int   $depth
    *
    * @param array $used
    *
    * @return string
    */
   private function getWidgetsListFromWidgetsArray($widgetsarray, $classname, $depth = 2, $used = [], $gslist = []) {
      $wl = "";

      if (is_array($widgetsarray) && count($widgetsarray) > 0) {

         foreach ($widgetsarray as $widgetId => $widgetTitle) {

            //We check if this widget is a real widget
            if (!is_array($widgetTitle)) {
               //If no 'title' is specified it won't be 'widgetid' => 'widget Title' but 'widgetid' so
               if (is_numeric($widgetId)) {
                  $widgetId = $widgetTitle;
               }
               $this->widgets[$classname][$widgetId] = -1;
               if (isset($gslist[$widgetId])) {
                  $gsid = $gslist[$widgetId];
                  if (!in_array($gsid, $used)) {
                     $wl .= "<tr><td id='btnAddWidgete" . $widgetId . "'"
                            . " class='plugin_mydashboard_menuDashboardListItem' "
                            . " data-widgetid='" . $gsid . "'"
                            . " data-classname='" . $classname . "'>";
                     $wl .= $widgetTitle;

                     if ($_SESSION['glpi_use_mode'] == Session::DEBUG_MODE) {
                        $wl .= " (" . $gsid . ")";
                     }/*->getWidgetListTitle()*/
                     $wl .= "</td></tr>";
                  }
               }
            } else { //If it's not a real widget
               //It may/must be an array of widget, in this case we need to go deeper (increase $depth)

               if (isset($widgetTitle['title'])) {
                  //If no 'title' is specified it won't be 'widgetid' => 'widget Title' but 'widgetid' so
                  if (is_numeric($widgetId)) {
                     $widgetId = $widgetTitle;
                  }
                  $this->widgets[$classname][$widgetId] = -1;
                  if (isset($gslist[$widgetId])) {
                     $gsid = $gslist[$widgetId];
                     if (!in_array($gsid, $used)) {
                        $wl .= "<tr><td id='btnAddWidgete" . $widgetId . "'"
                               . " class='media plugin_mydashboard_menuDashboardListItem' "
                               . " data-widgetid='" . $gsid . "'"
                               . " data-classname='" . $classname . "'>";

                        $icon = $widgetTitle['icon'] ?? "";
                        if (!empty($icon)) {
                           $wl .= "<div class='media-left'><i class='$icon fa-3x'></i></div>";
                        }
                        $wl .= "<div class='media-body' style='margin: 10px;'>";
                        $wl .= $widgetTitle['title'];
                        if ($_SESSION['glpi_use_mode'] == Session::DEBUG_MODE) {
                           $wl .= " (" . $gsid . ")";
                        }
                        $comment = $widgetTitle['comment'] ?? "";
                        if (!empty($comment)) {
                           $wl .= "<br><span class='widget-comment'>$comment</span>";
                        }
                        $wl .= "</div></td></tr>";
                     }
                  }
               } else {
                  $tmp = "<tr><td class='media plugin_mydashboard_menuDashboardList'>";
                  $tmp .= "<span class='media-left'>";
                  $tmp .= "<i class='far fa-folder fa-2x'></i>";
                  $tmp .= "</span>";
                  $tmp .= "<h6 class='media-body plugin_mydashboard_menuDashboardListTitle$depth'>";
                  $tmp .= $widgetId;
                  $tmp .= "</h6>";
                  $tmp .= "<table style='width: 100%;' class='plugin_mydashboard_menuDashboardList$depth'>";
                  $res = $this->getWidgetsListFromWidgetsArray($widgetTitle, $classname, $depth + 1, $used, $gslist);
                  if ($res != '') {
                     $tmp .= $res;
                  }
                  $tmp .= "</table></td></tr>";
                  if ($res != '') {
                     $wl .= $tmp;
                  }
               }
            }
         }
      }
      return $wl;
   }

   /**
    * Get an array of widgetNames as ["id1","id2"] for a specifid users_id
    *
    * @param int $id user id
    *
    * @return array of string
    */
   private function getDashboardForUser($id) {
      $this->interface = (Session::getCurrentInterface() == 'central') ? 1 : 0;
      $user_widget     = new PluginMydashboardUserWidget($id, $this->interface);
      return $user_widget->getWidgets();
   }

   //   /**
   //    * Get the widget index on dash, to add it in the correct order
   //    *
   //    * @param type $name
   //    *
   //    * @return int if $name is in self::dash, FALSE otherwise
   //    */
   //   private function getIndexOnDash($name) {
   //      return array_search($name, $this->dashboard);
   //   }

   /**
    * Get all plugin names of plugin hooked with mydashboard
    * @return array of string
    * @global type $PLUGIN_HOOKS
    */
   private function getPluginsNames() {
      global $PLUGIN_HOOKS;
      $plugins_hooked = (isset($PLUGIN_HOOKS['mydashboard']) ? $PLUGIN_HOOKS['mydashboard'] : []);
      $tab            = [];
      foreach ($plugins_hooked as $plugin_name => $x) {
         $tab[$plugin_name] = $this->getLocalName($plugin_name);
      }
      return $tab;
   }

   /**
    * Get the translated name of the plugin $plugin_name
    *
    * @param string $plugin_name
    *
    * @return string
    */
   private function getLocalName($plugin_name) {
      $infos = Plugin::getInfo($plugin_name);
      return isset($infos['name']) ? $infos['name'] : $plugin_name;
   }

   /**
    * Display an information in the top left corner of the mydashboard
    *
    * @param type $text
    */
   //    private function displayInfo($text) {
   //        if(is_string($text)) {
   //            $this->infos .= $text;
   //        }
   //    }

   /**
    * Get all languages for a specific library
    *
    * @param $libraryname
    *
    * @return array $languages
    * @internal param string $name name of the library :
    *    Currently available :
    *        sDashboard (for Datatable),
    *        mydashboard (for our own)
    */
   public function getJsLanguages($libraryname) {

      $languages = [];
      switch ($libraryname) {
         case "datatables" :
            $languages['sEmptyTable']    = __('No data available in table', 'mydashboard');
            $languages['sInfo']          = __('Showing _START_ to _END_ of _TOTAL_ entries', 'mydashboard');
            $languages['sInfoEmpty']     = __('Showing 0 to 0 of 0 entries', 'mydashboard');
            $languages['sInfoFiltered']  = __('(filtered from _MAX_ total entries)', 'mydashboard');
            $languages['sInfoPostFix']   = __('');
            $languages['sInfoThousands'] = __(',');
            //$languages['aLengthMenu']     = __('Show _MENU_ entries', 'mydashboard');
            $languages['sLoadingRecords'] = __('Loading') . "...";
            $languages['sProcessing']     = __('Processing') . "...";
            $languages['sSearch']         = __('Search') . ":";
            $languages['sZeroRecords']    = __('No matching records found', 'mydashboard');
            $languages['oPaginate']       = [
               'sFirst'    => __('First'),
               'sLast'     => __('Last'),
               'sNext'     => " " . __('Next'),
               'sPrevious' => __('Previous')
            ];
            $languages['oAria']           = [
               'sSortAscending'  => __(': activate to sort column ascending', 'mydashboard'),
               'sSortDescending' => __(': activate to sort column descending', 'mydashboard')
            ];
            $languages['select']          = [
               "rows" => [
                  "_" => "",// __('You have selected %d rows', 'mydashboard')
                  //                  "0" => "Click a row to select",
                  "1" => __('1 row selected', 'mydashboard')
               ]
            ];

            $languages['close']    = __("Close", "mydashboard");
            $languages['maximize'] = __("Maximize", "mydashboard");
            $languages['minimize'] = __("Minimize", "mydashboard");
            $languages['refresh']  = __("Refresh", "mydashboard");
            $languages['buttons']  = [
               'colvis'     => __('Column visibility', 'mydashboard'),
               "pageLength" => [
                  "_"  => __('Show %d elements', 'mydashboard'),
                  "-1" => __('Show all', 'mydashboard'),
               ],
            ];
            break;
         case "mydashboard" :
            $languages["dashboardsliderClose"]   = __("Close", "mydashboard");
            $languages["dashboardsliderOpen"]    = __("Dashboard", 'mydashboard');
            $languages["dashboardSaved"]         = __("Dashboard saved", 'mydashboard');
            $languages["dashboardNotSaved"]      = __("Dashboard not saved", 'mydashboard');
            $languages["dataReceived"]           = __("Data received for", 'mydashboard');
            $languages["noDataReceived"]         = __("No data received for", 'mydashboard');
            $languages["refreshAll"]             = __("Updating all widgets", 'mydashboard');
            $languages["widgetAddedOnDashboard"] = __("Widget added on Dashboard", "mydashboard");
            break;
      }
      return $languages;
   }

   /**
    * Get the names of each view
    * @return array of string
    */
   public function getViewNames() {

      $names = [];

      $names[self::$TICKET_REQUESTERVIEW] = _n('Ticket', 'Tickets', 2) . " (" . __("Requester") . ")";
      $names[self::$TICKET_TECHVIEW]      = _n('Ticket', 'Tickets', 2) . " (" . __("Technician") . ")";
      $names[self::$PROBLEM_VIEW]         = _n('Problem', 'Problems', 2);
      $names[self::$CHANGE_VIEW]          = _n('Change', 'Changes', 2);
      $names[self::$GROUP_VIEW]           = __('Group View');
      $names[self::$MY_VIEW]              = __('Personal View');
      $names[self::$GLOBAL_VIEW]          = __('Global View');
      $names[self::$RSS_VIEW]             = _n('RSS feed', 'RSS feeds', 2);
      $names[self::$PROJECT_VIEW]         = _n('Project', 'Projects', 2);

      return $names;
   }

   /**
    * Log $msg only when DEBUG_MODE is set
    *
    * @param int $active_profile
    */
   //   private function debug($msg) {
   //      if ($_SESSION['glpi_use_mode'] == Session::DEBUG_MODE) {
   //         Toolbox::logDebug($msg);
   //      }
   //   }


   /***********************/

   /**
    * @param int $active_profile
    */
   function loadDashboard($active_profile = -1, $predefined_grid = 0) {
      global $CFG_GLPI, $GLPI_CACHE;

      $rand           = mt_rand();
      $this->users_id = Session::getLoginUserID();
      $this->showMenu($rand, $this->users_id, $active_profile, $predefined_grid);

      $this->initDBWidgets();
      $grid = [];

      $list = $this->getDashboardForUser($this->users_id);
      $data = [];
      if (count($list) > 0) {
         foreach ($list as $k => $v) {
            $id = PluginMydashboardWidget::getGsID($v);
            if ($id) {
               $data[] = ["id" => $id, "x" => 6, "y" => 6, "width" => 4, "height" => 6];
            }
         }
         $grid = json_encode($data);
      }
      //LOAD WIDGETS
      $edit = PluginMydashboardPreference::checkEditMode(Session::getLoginUserID());
      $drag = PluginMydashboardPreference::checkDragMode(Session::getLoginUserID());
      //WITHOUTH PREFS
      $dashboard     = new PluginMydashboardDashboard();
      $options_users = ["users_id" => Session::getLoginUserID(), "profiles_id" => $active_profile];
      $id_user       = PluginMydashboardDashboard::checkIfPreferenceExists($options_users);

      if ($id_user == 0 || $edit == 2) {
         $options = ["users_id" => 0, "profiles_id" => $active_profile];
         $id      = PluginMydashboardDashboard::checkIfPreferenceExists($options);
         if ($dashboard->getFromDB($id)) {
            $grid = stripslashes($dashboard->fields['grid']);
         }
      }
      //WITH PREFS
      if ($edit != 2) {
         if ($dashboard->getFromDB($id_user)) {
            $grid = stripslashes($dashboard->fields['grid']);
         }
      }
      //LOAD PREDEFINED GRID
      if ($predefined_grid > 0) {
         $grid = PluginMydashboardDashboard::loadPredefinedDashboard($predefined_grid);
      }
      $datagrid = [];
      $datajson = [];
      $optjson  = [];
      $widgets  = [];
      if (!empty($grid) && ($datagrid = json_decode($grid, true)) == !null) {

         $widgetclasse = new PluginMydashboardWidget();
         //            $ckey         = 'md_cache_' . md5($widgetclasse->getTable()).Session::getLoginUserID();
         //            $datas     = $GLPI_CACHE->get($ckey);
         //UNACTIVATE IT FOR DEBUG
         //            if (is_array($datas) && count($datas) > 0 && $predefined_grid == 0) {
         //               $datajson = $datas;
         //            } else {
         $widgets = PluginMydashboardWidget::getWidgetList();

         foreach ($datagrid as $k => $v) {
            if (isset($v["id"])) {
               $datajson[$v["id"]] = PluginMydashboardWidget::getWidget($v["id"], $widgets, []);

               if (isset($_SESSION["glpi_plugin_mydashboard_widgets"])) {
                  foreach ($_SESSION["glpi_plugin_mydashboard_widgets"] as $w => $r) {
                     if (isset($widgets[$v["id"]]["id"])
                         && $widgets[$v["id"]]["id"] == $w) {
                        $optjson[$v["id"]]["enableRefresh"] = $r;
                     }
                  }
               }
            }
         }
         //               if ($predefined_grid == 0) {
         //                  $GLPI_CACHE->set($ckey, $datajson);
         //               }
         //            }
      } else {
         echo "<div class='alert alert-warning' id='warning-alert'>
                <strong>" . __('Warning', 'mydashboard') . "!</strong>
                " . __('No widgets founded, please add widgets', 'mydashboard') . "
            </div>";
         //         echo Html::scriptBlock('$("#warning-alert").fadeTo(2000, 500).slideUp(500, function(){
         //            $("#success-alert").slideUp(500);
         //         });');

         $grid = json_encode($grid);
      }

      $datajson = json_encode($datajson);
      $optjson  = json_encode($optjson);

      //FOR ADD NEW WIDGET
      $allwidgetjson = [];

      if ($edit > 0) {

         if (isset($_SESSION["glpi_plugin_mydashboard_allwidgets"])
             && count($_SESSION["glpi_plugin_mydashboard_allwidgets"]) > 0) {
            $allwidgetjson = $_SESSION["glpi_plugin_mydashboard_allwidgets"];
         } else {
            //            if (empty($grid) && count($widgets) < 1) {
            $widgets = PluginMydashboardWidget::getWidgetList();
            //            }
            foreach ($widgets as $k => $val) {
               $allwidgetjson[$k] = ["<div class='alert alert-success' id='success-alert'>
                <strong>" . __('Success', 'mydashboard') . "</strong> -
                " . __('Save grid to see widget', 'mydashboard') . "
            </div>"];
               //NOT LOAD ALL WIDGETS FOR PERF
               //               $allwidgetjson[$k] = PluginMydashboardWidget::getWidget($k, [], $widgets);

            }
         }
      }
      $allwidgetjson = json_encode($allwidgetjson);
      $msg_delete    = __('Delete widget', 'mydashboard');
      $msg_error     = __('No data available', 'mydashboard');
      $msg_refresh   = __('Refresh widget', 'mydashboard');
      $disableResize = 'true';
      $disableDrag   = 'true';
      $delete_button = 'false';

      //      if ($this->interface == 1) {
      if ($drag > 0) {
         $disableResize = 'false';
         $disableDrag   = 'false';
      }
      if ($edit > 0) {
         $delete_button = 'true';
      }
      //      }

      echo "<div id='mygrid$rand' class='mygrid'>";
      echo "<div id='grid-stack$rand' class='grid-stack$rand grid-stack md-grid-stack'>";
      echo "</div>";

      echo Html::scriptBlock("
        var myDashboardConfig = {
          disableResize: $disableResize,
          disableDrag: $disableDrag,
          deleteButton: $delete_button,
          rand: $rand,
          msg: {
            delete: '$msg_delete',
            error: '$msg_error',
            refresh: '$msg_refresh'
          },
          grid: $grid,
          optjson: $optjson,
          datajson: $datajson,
          allwidgetjson: $allwidgetjson,
          active_profile: $active_profile,
        };
      ");
      echo Html::script('plugins/mydashboard/js/menu.js');

      echo "</div>";
   }
}

