$(function () {
  var options = {
    cellHeight: 41,
    verticalMargin: 2,
    disableResize: myDashboardConfig.disableResize,
    disableDrag: myDashboardConfig.disableDrag,
    resizable: {
      handles: 'e, se, s, sw, w'
    },
    handleClass: 'card-header'
  };
  this.grid = GridStack.init(options, document.getElementById('grid-stack' + myDashboardConfig.rand));
  new function () {
    this.serializedData = myDashboardConfig.grid;
    this.grid = document.getElementById('grid-stack' + myDashboardConfig.rand).gridstack;
    this.loadGrid = function () {
      this.grid.removeAll();
      var items = GridStack.Utils.sort(this.serializedData);
      items.forEach(function(node)  {
        var nodeid = node.id;
        var optArray = myDashboardConfig.optjson;
        var widgetArray = myDashboardConfig.datajson;
        var widget = widgetArray['' + nodeid + ''];
        if ( widget !== undefined ) {
          widget = widgetArray['' + nodeid + ''];
        } else {
          widget = myDashboardConfig.msg.error;
        }
        var opt = optArray['' + nodeid + ''];
        if ( opt !== undefined ) {
          options = optArray['' + nodeid + ''];
          if ( options != null ) {
            refreshopt = optArray['' + nodeid + '']['enableRefresh'];
          } else {
            refreshopt = false;
          }
        } else {
          refreshopt = false;
        }
        var delbutton = '';
        var refreshbutton = '';
        if (myDashboardConfig.deleteButton == 1) {
          var delbutton = '<button title="' + myDashboardConfig.msg.delete + '" class="md-button pull-left" onclick="deleteWidget(\'' + node.id + '\');"><i class="fas fa-times"></i></button>';
        }
        if (refreshopt == 1) {
          var refreshbutton = '<button title="' + myDashboardConfig.msg.refresh + '" class="md-button refresh-icon pull-right" onclick="refreshWidget(\'' + node.id + '\');"><i class="fas fa-sync-alt"></i></button>';
        } else {
          var refreshbutton = '<button title="' + myDashboardConfig.msg.refresh + '" class="md-button refresh-icon-disabled pull-right"><i class="fas fa-sync-alt"></i></button>';
        }
        if ( nodeid !== undefined ) {
          var el = '<div class="grid-stack-item-content md-grid-stack-item-content card"><div class="card-header cursor-grab">' + refreshbutton + delbutton + '</div>'
            + '<div class="card-content overflow-y-auto overflow-x-hidden">' + widget + '</div></div>';
          this.grid.addWidget(el, {x: node.x, y: node.y, w: node.width, h: node.height, id: node.id});
          refreshWidget(nodeid);
        }
      }, this);
      return false;
    }.bind(this);

    this.loadGrid();
  };
});


function launchloadWidgets() {
  var modal = $('<div>').dialog({ modal: true });
  modal.dialog('widget').hide();
  $('#ajax_loader').show();
  $.ajax({
    url: CFG_GLPI.root_doc + "/plugins/mydashboard/ajax/loadWidgets.php",
    type: 'POST',
    complete: function () {
      //back to normal!
        $('#ajax_loader').hide();
      modal.dialog('close');
      window.location.href = CFG_GLPI.root_doc + "/plugins/mydashboard/front/menu.php";
    }
  });
}
function launchClearGrid() {
  $('#ajax_loader').show();
  $.ajax({
    url: CFG_GLPI.root_doc + "/plugins/mydashboard/ajax/clearGrid.php",
    type: 'POST',
    success:function() {
      $('#ajax_loader').hide();
      window.location.href = CFG_GLPI.root_doc + '/plugins/mydashboard/front/menu.php';
    }
  });
}
function launchFullscreen() {
  $('#mygrid' + myDashboardConfig.rand).toggleFullScreen();
  $('#mygrid' + myDashboardConfig.rand).toggleClass('fullscreen_view');
}
function launchEditMode() {
  $('#ajax_loader').show();
  $.ajax({
    url: CFG_GLPI.root_doc + "/plugins/mydashboard/ajax/editGrid.php",
    type: 'POST',
    data:{edit_mode:1},
    success:function() {
      $('#ajax_loader').hide();
      window.location.href = CFG_GLPI.root_doc + '/plugins/mydashboard/front/menu.php';
    }
  });
}
function launchEditDefaultMode() {
  $('#ajax_loader').show();
  $.ajax({
    url: CFG_GLPI.root_doc + '/plugins/mydashboard/ajax/editGrid.php',
    type: 'POST',
    data:{edit_mode:2},
    success:function() {
      $('#ajax_loader').hide();
      window.location.href = CFG_GLPI.root_doc + '/plugins/mydashboard/front/menu.php';
    }
  });
}
function launchCloseEditMode() {
  $('#ajax_loader').show();
  $.ajax({
    url: CFG_GLPI.root_doc + '/plugins/mydashboard/ajax/editGrid.php',
    type: 'POST',
    data:{edit_mode:0},
    success:function() {
      $('#ajax_loader').hide();
      window.location.href = CFG_GLPI.root_doc + '/plugins/mydashboard/front/menu.php';
    }
  });
}
function launchDragGrid() {
  $('#ajax_loader').show();
  $.ajax({
    url: CFG_GLPI.root_doc + '/plugins/mydashboard/ajax/dragGrid.php',
    type: 'POST',
    data:{drag_mode:1},
    success:function() {
      $('#ajax_loader').hide();
      window.location.href = CFG_GLPI.root_doc + '/plugins/mydashboard/front/menu.php';
    }
  });
}
function launchUndragGrid() {
  $('#ajax_loader').show();
  $.ajax({
    url: CFG_GLPI.root_doc + '/plugins/mydashboard/ajax/dragGrid.php',
    type: 'POST',
    data:{drag_mode:0},
    success:function() {
      $('#ajax_loader').hide();
      window.location.href = CFG_GLPI.root_doc + '/plugins/mydashboard/front/menu.php';
    }
  });
}

function launchSaveGrid() {
  // Get the GridStack instance (you can get the grid using $('.grid-stack').data('gridstack') if not already available)
  var grid = GridStack.init(); // or access your grid instance if already initialized

  // Serialize the data of visible grid items
  this.serializedData = _.map(grid.engine.nodes, function (node) {
    // Check if the node is visible
    if ($(node.el).is(':visible') && node.id !== undefined) {
      return {
        id: node.id,
        x: node.x,
        y: node.y,
        width: node.w, // Use `w` and `h` for width and height in Gridstack v2
        height: node.h
      };
    }
  });

  var sData = JSON.stringify(this.serializedData);

  // Show loader
  $('#ajax_loader').show();

  // Send the serialized data via AJAX
  $.ajax({
    url: CFG_GLPI.root_doc + '/plugins/mydashboard/ajax/saveGrid.php',
    type: 'POST',
    data: {
      data: sData,
      profiles_id: myDashboardConfig.active_profile
    },
    success: function() {
      $('#ajax_loader').hide();
      window.location.href = CFG_GLPI.root_doc + '/plugins/mydashboard/front/menu.php';
    }
  });
}

function launchSaveDefaultGrid() {
  this.serializedData = _.map($('.grid-stack' + myDashboardConfig.rand + ' > .grid-stack-item:visible'), function (el) {
    el = $(el);
    var node = el.data('_gridstack_node');
    return {
      id: node.id,
      x: node.x,
      y: node.y,
      width: node.width,
      height: node.height
    };
  }, this);
  var sData = JSON.stringify(this.serializedData);
  var users_id = 0;
  $('#ajax_loader').show();
  $.ajax({
    url: CFG_GLPI.root_doc + '/plugins/mydashboard/ajax/saveGrid.php',
    type: 'POST',
    data:{data:sData,users_id:users_id,profiles_id:$active_profile},
    success:function(data) {
      $('#ajax_loader').hide();
      var redirectUrl = CFG_GLPI.root_doc + '/plugins/mydashboard/front/menu.php';
      var form = $('<form action=\"' + redirectUrl + '\" method=\"post\">' +
        '<input type=\"hidden\" name=\"profiles_id\" value=\"$active_profile\"></input>' +
        '<input type=\"hidden\" name=\"_glpi_csrf_token\" value=\"' + data +'\"></input>'+
        '</form>');
      $('body').append(form);
      $(form).submit();
    }
  });
}
function deleteWidget(id) {
  this.grid = document.getElementById('grid-stack' + myDashboardConfig.rand).gridstack;
  widget = document.querySelector('div[gs-id='+ id + ']');
  this.grid.removeWidget(widget);
  return false;
};
function addNewWidget(value) {
  var id = value;
  if (id != 0){
    var widgetArray = myDashboardConfig.allwidgetjson;
    widget = widgetArray['' + id + ''];
    var el = '<div><div class=\"grid-stack-item-content md-grid-stack-item-content\">' +
      '<button class=\"md-button pull-left\" onclick=\"deleteWidget(\'' + id + '\');\">' +
      '<i class=\"fas fa-times\"></i></button>' + widget + '<div/><div/>';
    var grid = document.getElementById('grid-stack' + myDashboardConfig.rand).gridstack;
    grid.addWidget(el, {x: 0, y: 0, w: 4, h: 12, id});
    return true;
  }
  return false;
};
function refreshWidget (id) {
  var widgetOptionsObject = [];
  $.ajax({
    url: CFG_GLPI.root_doc + '/plugins/mydashboard/ajax/refreshWidget.php',
    type: 'POST',
    data:{gsid:id, params:widgetOptionsObject},
    dataType: 'json',
    success:function(data) {
      var wid = data.id;
      var wdata = data.widget;
      var widget = $('div[id='+ wid + ']');
      widget.replaceWith(wdata);
    }
  });
  return false;
};
function refreshWidgetByForm (id, gsid, formId) {
  var widgetOptions = $('#' + formId).serializeArray();
  var widgetOptionsObject = {};
  $.each(widgetOptions,
    function (i, v) {
      var name = v.name;
      // Remove [] in the name do issue with ajax
      var index = v.name.indexOf('[]');
      if( index != -1 ){
        name = v.name.substring(0, index);
      }
      // Key already exist
      if(name in widgetOptionsObject){
        if(widgetOptionsObject[name] instanceof Array){
          widgetOptionsObject[name].push(v.value);
        }else{
          var tempArray = [];
          tempArray.push(widgetOptionsObject[name]);
          tempArray.push(v.value);
          widgetOptionsObject[name] = tempArray;
        }
      }else{
        widgetOptionsObject[name] = v.value;
      }
    }
  );
  var widget = $('div[id='+ id + ']');
  $.ajax({
    url: CFG_GLPI.root_doc + '/plugins/mydashboard/ajax/refreshWidget.php',
    type: 'POST',
    data:{
      gsid:gsid,
      params:widgetOptionsObject,
      id:id
    },
    success:function(data) {
      widget.replaceWith(data);
    }
  });
  return false;
};


function downloadGraph(id) {
  html2canvas(document.getElementById(id), {
    onrendered: function(canvas) {
      var link = document.createElement('a');
      link.href = canvas.toDataURL('image/png');

      if (!HTMLCanvasElement.prototype.toBlob) {
        Object.defineProperty(HTMLCanvasElement.prototype, 'toBlob', {
          value: function (callback, type, quality) {
            var canvas = this;
            setTimeout(function() {
              var binStr = atob( canvas.toDataURL(type, quality).split(',')[1] ),
                len = binStr.length,
                arr = new Uint8Array(len);

              for (var i = 0; i < len; i++ ) {
                arr[i] = binStr.charCodeAt(i);
              }

              callback( new Blob( [arr], {type: type || 'image/png'} ) );
            });
          }
        });
      }

      canvas.toBlob(function(blob){
        link.href = URL.createObjectURL(blob);
        saveAs(blob, 'myChart.png');
      },'image/png');
    }
  })
}
