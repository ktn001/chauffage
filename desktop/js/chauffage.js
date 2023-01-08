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

$(function() {
  $.datetimepicker.setLocale(jeedom_langage.substr(0,2))
  datetimepicker_theme = ''
  if (jeedom.theme.default_bootstrap_theme.toLowerCase().includes('dark')){
    datetimepicker_theme = 'dark'
  }
})

/* Permet la réorganisation des commandes dans l'équipement */
$("#table_cmd").sortable({
  axis: "y",
  cursor: "move",
  items: ".cmd",
  placeholder: "ui-state-highlight",
  tolerance: "intersect",
  forcePlaceholderSize: true
})

/* Permet la réorganisation des zones */
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

/* Choix d'une info pour les calculs */
$('#table_cmd').delegate('.listEquipementInfo', 'click', function() {
  var el = $(this)
  jeedom.cmd.getSelectModal({ cmd: {type: 'info' } }, function(result) {
    var calcul = el.closest('tr').find('.cmdAttr[data-l1key=configuration][data-l2key=' + el.data('input') + ']')
    calcul.atCaret('insert',result.human)
  })
})

/* Récupère toutes les consignes dans un array */
function schedulesFromTable() {
  let schedules = []
  $('#table_schedules .schedAttr[data-l1key=consigne]').each(function(){
    consigne = $(this).val().trim()
    if (consigne.length > 0) {
      schedule = {}
      schedule.key = $(this).closest('td').data('sched_key')
      schedule.zoneid = $(this).closest('td').data('zoneid')
      schedule.consigne = consigne
      schedules.push(schedule)
    }
  })
  return schedules
}

/* Construction de la table des schedules */
function buildSchedulesTable() {
  jours = {
    '1': '{{lun}}',
    '2': '{{mar}}',
    '3': '{{mer}}',
    '4': '{{jeu}}',
    '5': '{{ven}}',
    '6': '{{sam}}',
    '7': '{{dim}}'
  }
  tr = '<tr>'
  tr += '<th>{{Heure}}</th>'
  $('#table_zones tbody tr').each(function(index) {
    zoneId = $(this).find('[data-l1key=id]').value()
    zoneName = $(this).find('[data-l1key=name]').value()
    for (jour in jours) {
      tr += '<th data-jour="' + jour + '" data-jourtxt="' + jours[jour] + '" data-zoneId="' + zoneId + '" data-zoneName="' + zoneName + '"></th>'
    }
  })
  tr += '</tr>'
  $('#table_schedules thead').empty().append(tr)
  $('#table_schedules tbody').empty()
  for (heure = 0; heure < 24; heure++) {
    for (minute of ['00', '30']) {
      tr = '<tr>'
      tr += '<td class="scheduletime">' + heure + ':' + minute + '</td>'
      $('#table_zones tbody tr').each(function(index) {
        zoneId = $(this).find('[data-l1key=id]').value()
        for (jour in jours) {
          tr += '<td data-sched_key="' + jour + String(heure).padStart(2,'0') + String(minute).padStart(2,'0') + '" data-jour="' + jour + '" data-zoneId="' + zoneId + '">'
          tr += '<input class="schedAttr form-control input-sm" data-l1key="consigne">'
          tr += '</td>'
        }
      })
      tr += '</tr>'
      $('#table_schedules tbody').append(tr)
    }
  }
}

/* Restore des sechdules dans la table */
function fillSchedulesTable(schedules) {
  for (schedule of schedules) {
    $('#table_schedules td[data-zoneId=' + schedule.zoneid + '][data-sched_key=' + schedule.key + '] input.schedAttr[data-l1key]').val(schedule.consigne)
  }
}

/* Reconstuction de la table des schedules avec conservation des données */
function rebuildSchedulesTable() {
  let schedules = schedulesFromTable()
  buildSchedulesTable()
  fillSchedulesTable(schedules)
    if ($('input[name=selectvue]:checked').val() == 'zone') {
      $('#selectZone').trigger('change')
  } else if ($('input[name=selectvue]:checked').val() == 'jour') {
    $('#selectJour').trigger('change')
  }
}

/* Complète les données à enregister en y ajoutant les zones les schedules et les absences */
function saveEqLogic(eqLogic){
  if (!isset(eqLogic.configuration)) {
    eqLogic.configuration = {}
  }
  eqLogic.configuration.zones = $('.zone').getValues('.zoneAttr')
  eqLogic.configuration.schedules = schedulesFromTable()
  eqLogic.configuration.absences = $('.absence').getValues('.absenceAttr')
  return eqLogic
}

/* Sur modification de zone */
$('#table_zones').on('change sortstop', function(){
  modifyWithoutSave = true
  if ($('input[name=selectvue]:checked').val() == 'zone') {
    buildSelectZone()
  }
  rebuildSchedulesTable()
})

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

/* Mise à jour du selecteur de zone */
function buildSelectZone() {
  preSelected = $('#selectZone').val()
  $('#selectZone').empty()
  options = ''
  $('#table_zones tbody tr').each(function(index) {
    zoneId = $(this).find('[data-l1key=id]').value()
    zoneName = $(this).find('[data-l1key=name]').value()
    selected = (preSelected == zoneId) ? 'selected' : ''
    options += '<option value="' + zoneId + '" ' + selected + '>' + zoneName + '</option>'
  })
  $('#selectZone').append(options)
}

/* changement du type de vue des schedules */
$('input[name=selectvue]').on('change', function() {
  let value = $(this).val()
  if (value == 'jour') {
    $('#selectionZone').addClass('hidden')
    $('#selectionJour').removeClass('hidden')
    $('#selectJour').trigger('change')
  } else if (value == 'zone'){
    buildSelectZone()
    $('#selectionJour').addClass('hidden')
    $('#selectionZone').removeClass('hidden')
    $('#selectZone').trigger('change')
  }
})

/* Changement de jour à montrer */
$('#selectJour').on('change', function() {
  if ($('input[name=selectvue]:checked').val() != 'jour') {
    return
  }
  selected = $('#selectJour').val()
  $('#table_schedules [data-jour]').each(function(){
    if ($(this).data('jour') == selected) {
      $(this).html($(this).data('zonename'))
      $(this).show()
    } else {
      $(this).hide()
    }
  })
})

/* Changement de zone à montrer */
$('#selectZone').on('change', function() {
  if ($('input[name=selectvue]:checked').val() != 'zone') {
    return
  }
  selected = $('#selectZone').val()
  $('#table_schedules [data-zoneid]').each(function(){
    if ($(this).data('zoneid') == selected) {
      $(this).html($(this).data('jourtxt'))
      $(this).show()
    } else {
      $(this).hide()
  }
  })
})

/* Renseignement de la table des zones */
function printEqLogic(data) {
  $('#table_zones tbody').empty()
  for (zone of data.configuration.zones) {
    addZoneToTable (zone)
  }
  buildSchedulesTable()
  fillSchedulesTable(data.configuration.schedules)
  $('#selectJour').trigger('change')
  $('#selectZone').trigger('change')
  for (absence of data.configuration.absences) {
    addAbsenceToTable(absence)
  }
}

/* Validation de la syntaxe d'une consigne */
$('#table_schedules').delegate('input.schedAttr[data-l1key=consigne]','keyup',function() {
  if ($(this).val().match(/^\s*(\d+(\.\d*)?)?\s*$/)) {
    $(this).removeClass("errorConsigne")
  } else {
    $(this).addClass("errorConsigne")
  }
})

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
        nextId = data.result
      }
    })
    _zone.id=nextId
  }
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
  tr += '<input class="tooltips cmdAttr form-control input-sm disabled" data-l1key="logicalId">'
  tr += '</td>'
  tr += '<td>'
  if ( _cmd.logicalId.substring(0,9) != 'consigne_'){
    tr += '<textarea class="cmdAttr form-control input-sm" data-l1key="configuration" data-l2key="calcul" style="height:35px;" placeholder="{{Calcul}}"></textarea>'
    tr += '<a class="btn btn-default listEquipementInfo btn-xs" data-input="calcul" style="width:100%;margin-top:2px;"><i class="fas fa-list-alt"></i> {{Rechercher équipement}}</a>'
  }
  tr += '</td>'
  tr += '<td>'
  if ( _cmd.logicalId.substring(0,9) != 'consigne_'){
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
  tr += '<i class="fas fa-minus-circle pull-right cmdAction cursor" data-action="remove" title="{{Supprimer la commande}}"></i></td>'
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

/* Ajout d'une absence */
$('.absenceAction[data-action=add]').on('click',function() {
  addAbsenceToTable()
})

function addAbsenceToTable(_absence) {
  let tr = '<tr class="absence">'
  tr += '<td>'
  tr += '<input type="text" class="absenceAttr datetimepicker" data-l1key="du"/>'
  tr += '</td>'
  tr += '<td>'
  tr += '<input type="text" class="absenceAttr datetimepicker" data-l1key="au"/>'
  tr += '</td>'
  tr += '<td>'
  tr += '<i class="fas fa-minus-circle pull-right absenceAction cursor" data-action="remove" title="{{Supprimer l\'absence}}"></i></td>'
  tr += '</td>'
  tr += '</tr>'
  $('#table_absences tbody').append(tr)
  tr = $('#table_absences tbody tr').last()
  tr.find('.datetimepicker').each(function () {
    $(this).datetimepicker({
      step: 30,
      dayOfWeekStart : 1,
      theme : datetimepicker_theme,
      minDate: Date.now(),
      format: 'd/m/Y H:i:s',
    })
  })
  if (isset(_absence)){
    tr.setValues(_absence, '.absenceAttr')
  }
}

/* Changement de date de début d'absence */
$('#table_absences tbody').on('change','.absenceAttr[data-l1key=du]', function(e) {
  fromDate = $(this).datetimepicker('getValue')
  $(this).closest('tr').find('.absenceAttr[data-l1key=au]').datetimepicker('setOptions',{minDate:fromDate})
})

/* Suppression d'une absence */
$('#table_absences tbody').on('click','.absenceAction[data-action=remove]', function() {
  $(this).closest('tr').remove()
})
