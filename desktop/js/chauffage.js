/* This file is part of Jeedom.
*
* Jeedom is free software: you can redistribute it and/or modify
* it under the terms of the GNU General Public License as published by
* the Free Software Foundation, either version 3 of the License, or
* (at your option) any later version.
*
* Jeedom is distributed in the hope that it will be useful,
* but WITHOUT ANY WARRANTY; without even the implied warranty of
* MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
* GNU General Public License for more details.
*
* You should have received a copy of the GNU General Public License
* along with Jeedom. If not, see <http://www.gnu.org/licenses/>.
*/

/* Permet la réorganisation des commandes dans l'équipement */
$("#table_cmd").sortable({
  axis: "y",
  cursor: "move",
  items: ".cmd",
  placeholder: "ui-state-highlight",
  tolerance: "intersect",
  forcePlaceholderSize: true
})
$("#table_zones").sortable({
  axis: "y",
  cursor: "move",
  items: ".zone",
  placeholder: "ui-state-highlight",
  tolerance: "intersect",
  forcePlaceholderSize: true
})

/* Création d'une commande info */
$('.cmdAction[data-action=add-info').on('click', function() {
  bootbox.prompt({
    title: "{{Type d'information}}",
    inputType: "select",
    inputOptions: [
      {text: '{{Temperature}}', value: 1},
      {text: '{{Ouvrant}}', value: 2}
    ],
    value: 1,
    callback: function (result){
      if (result == null) {
        return
      }
      if (result == 1) {
        cmd = {
          configuration: {},
          type : 'info',
          subType : 'numeric',
          logicalId : 'temperature'
        }
      } else if (result == 2) {
        cmd = {
          configuration: {},
          type : 'info',
          subType : 'binary',
          logicalId : 'ouvrant'
        }
      }
      addCmdToTable(cmd)
    }
  })
})

/* choix d'une info */
$('#table_cmd').delegate('.listEquipementInfo', 'click', function() {
  var el = $(this)
  jeedom.cmd.getSelectModal({ cmd: {type: 'info' } }, function(result) {
    var calcul = el.closest('tr').find('.cmdAttr[data-l1key=configuration][data-l2key=' + el.data('input') + ']')
    calcul.atCaret('insert',result.human)
  })
})

function saveEqLogic(eqLogic){
  if (!isset(eqLogic.configuration)) {
    eqLogic.configuration = {}
  }
  eqLogic.configuration.zones = $('.zone').getValues('.zoneAttr')
  return eqLogic
}

/* Sur modification de zone */
$('#table_zones').on({
  'change': function(event){
    modifyWithoutSave = true
  }
}, '.zoneAttr')

/* Bouton d'ajout d'une zone */
$('.zoneAction[data-action=add]').off('click').on('click', function() {
  addZoneToTable()
  modifyWithoutSave = true
})

/* Bouton de suppression d'une zone */
$('#table_zones').on('click', '.zone .zoneAction[data-action=remove]', function() {
  $(this).closest('tr').remove()
  modifyWithoutSave = true
})

/* Renseignement de la table des zones */
function printEqLogic(data) {
  for (zone of data.configuration.zones) {
    addZoneToTable (zone)
  }
}

function addZoneToTable(_zone) {
  if (!isset(_zone)){
    var _zone = {}
  }
  if (!isset(_zone.id)) {
    $.ajax({
      type: 'POST',
      url: 'plugins/chauffage/core/ajax/chauffage.ajax.php',
      async: false,
      data : {
        action: 'getNextZoneId',
        id : $('.eqLogicAttr[data-l1key=id]').value(),
      },
      dataType : 'json',
      global:false,
      error: function(request, status, error) {
        handleAjaxError(request, status, error)
      },
      success: function(data) {
        if (data.state != 'ok') {
          $.fn.showAlert({message: data.result, level: 'danger'})
          return ""
        }
        console.log(data.result);
        nextId = data.result
      }
    })
    _zone.id=nextId
  }
  console.log(_zone.id)
  var tr = '<tr class="zone">'
  tr += '<td class="hidden-xs">'
  tr += '<span class="zoneAttr" data-l1key="id"></span>'
  tr += '</td>'
  tr += '<td>'
  tr += '<input class="zoneAttr form-control input-sm" data-l1key="name" placeholder="{{Nom de la zone}}"/>'
  tr += '</td>'
  tr += '<td>'
  tr += '<input type="checkbox" class="zoneAttr" data-l1key="isEnable" checked/>'
  tr += '</td>'
  tr += '<td>'
  tr += '<i class="fas fa-minus-circle pull-right zoneAction cursor" data-action="remove" title="{{Supprimer la zone}}"></i></td>'
  tr += '</td>'
  tr += '</tr>'
  $('#table_zones tbody').append(tr)
  var tr = $('#table_zones tbody tr').last()
  tr.setValues(_zone, '.zoneAttr')
}

/* Fonction permettant l'affichage des commandes dans l'équipement */
function addCmdToTable(_cmd) {
  if (!isset(_cmd)) {
    var _cmd = {configuration: {}}
  }
  if (!isset(_cmd.configuration)) {
    _cmd.configuration = {}
  }
  if (init(_cmd.logicalId) == 'refresh') {
    return
  }
  var tr = '<tr class="cmd" data-cmd_id="' + init(_cmd.id) + '">'
  tr += '<td class="hidden-xs">'
  tr += '<span class="cmdAttr" data-l1key="id"></span>'
  tr += '<span class="cmdAttr hidden" data-l1key="logicalId"></span>'
  tr += '</td>'
  tr += '<td>'
  tr += '<div class="input-group">'
  tr += '<input class="cmdAttr form-control input-sm roundedLeft" data-l1key="name" placeholder="{{Nom de la commande}}">'
  tr += '<span class="input-group-btn"><a class="cmdAction btn btn-sm btn-default" data-l1key="chooseIcon" title="{{Choisir une icône}}"><i class="fas fa-icons"></i></a></span>'
  tr += '<span class="cmdAttr input-group-addon roundedRight" data-l1key="display" data-l2key="icon" style="font-size:19px;padding:0 5px 0 0!important;"></span>'
  tr += '</div>'
  tr += '<select class="cmdAttr form-control input-sm" data-l1key="value" style="display:none;margin-top:5px;" title="{{Commande info liée}}">'
  tr += '<option value="">{{Aucune}}</option>'
  tr += '</select>'
  tr += '</td>'
  tr += '<td>'
  tr += '<span class="type" type="' + init(_cmd.type) + '">' + jeedom.cmd.availableType() + '</span>'
  tr += '<span class="subType" subType="' + init(_cmd.subType) + '"></span>'
  tr += '</td>'
  tr += '<td>'
  if (! ['consigne'].includes(init(_cmd.logicalId))) {
    tr += '<textarea class="cmdAttr form-control input-sm" data-l1key="configuration" data-l2key="calcul" style="height:35px;" placeholder="{{Calcul}}"></textarea>'
    tr += '<a class="btn btn-default listEquipementInfo btn-xs" data-input="calcul" style="width:100%;margin-top:2px;"><i class="fas fa-list-alt"></i> {{Rechercher équipement}}</a>'
  }
  tr += '</td>'
  tr += '<td>'
  if (! ['consigne'].includes(init(_cmd.logicalId))) {
    tr += '<input class="tooltips cmdAttr form-control input-sm" data-l1key="configuration" data-l2key="zone" placeholder="{{Zone}}" title="{{Zone}}">'
  }
  tr += '</td>'
  tr += '<td>'
  tr += '<label class="checkbox-inline"><input type="checkbox" class="cmdAttr" data-l1key="isVisible" checked/>{{Afficher}}</label> '
  tr += '<label class="checkbox-inline"><input type="checkbox" class="cmdAttr" data-l1key="isHistorized"/>{{Historiser}}</label> '
  tr += '<label class="checkbox-inline"><input type="checkbox" class="cmdAttr" data-l1key="display" data-l2key="invertBinary"/>{{Inverser}}</label> '
  tr += '<div style="margin-top:7px;">'
  tr += '<input class="tooltips cmdAttr form-control input-sm" data-l1key="configuration" data-l2key="minValue" placeholder="{{Min}}" title="{{Min}}" style="width:30%;max-width:80px;display:inline-block;margin-right:2px;">'
  tr += '<input class="tooltips cmdAttr form-control input-sm" data-l1key="configuration" data-l2key="maxValue" placeholder="{{Max}}" title="{{Max}}" style="width:30%;max-width:80px;display:inline-block;margin-right:2px;">'
  tr += '<input class="tooltips cmdAttr form-control input-sm" data-l1key="unite" placeholder="Unité" title="{{Unité}}" style="width:30%;max-width:80px;display:inline-block;margin-right:2px;">'
  tr += '</div>'
  tr += '</td>'
  tr += '<td>';
  tr += '<span class="cmdAttr" data-l1key="htmlstate"></span>'; 
  tr += '</td>';
  tr += '<td>'
  if (is_numeric(_cmd.id)) {
    tr += '<a class="btn btn-default btn-xs cmdAction" data-action="configure"><i class="fas fa-cogs"></i></a> '
    tr += '<a class="btn btn-default btn-xs cmdAction" data-action="test"><i class="fas fa-rss"></i> Tester</a>'
  }
  if (! ['consigne'].includes(init(_cmd.logicalId))) {
    tr += '<i class="fas fa-minus-circle pull-right cmdAction cursor" data-action="remove" title="{{Supprimer la commande}}"></i></td>'
  }
  tr += '</tr>'
  $('#table_cmd tbody').append(tr)
  var tr = $('#table_cmd tbody tr').last()
  jeedom.eqLogic.buildSelectCmd({
    id:  $('.eqLogicAttr[data-l1key=id]').value(),
    filter: {type: 'info'},
    error: function (error) {
      $('#div_alert').showAlert({message: error.message, level: 'danger'})
    },
    success: function (result) {
      tr.find('.cmdAttr[data-l1key=value]').append(result)
      tr.setValues(_cmd, '.cmdAttr')
      jeedom.cmd.changeType(tr, init(_cmd.subType))
    }
  })
}
