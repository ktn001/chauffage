<?php
if (!isConnect('admin')) {
    throw new Exception('{{401 - Accès non autorisé}}');
}
// Déclaration des variables obligatoires
$plugin = plugin::byId('chauffage');
sendVarToJS('eqType', $plugin->getId());
$eqLogics = eqLogic::byType($plugin->getId());
?>

<div class="row row-overflow">
    <!-- Page d'accueil du plugin -->
    <div class="col-xs-12 eqLogicThumbnailDisplay">
        <legend><i class="fas fa-cog"></i>  {{Gestion}}</legend>
        <!-- Boutons de gestion du plugin -->
        <div class="eqLogicThumbnailContainer">
            <div class="cursor eqLogicAction logoPrimary" data-action="add">
                <i class="fas fa-plus-circle"></i>
                <br>
                <span>{{Ajouter}}</span>
            </div>
            <div class="cursor eqLogicAction logoSecondary" data-action="gotoPluginConf">
                <i class="fas fa-wrench"></i>
                <br>
                <span>{{Configuration}}</span>
            </div>
        </div>
        <legend><i class="fas fa-table"></i> {{Mes chauffages}}</legend>
        <?php
        if (count($eqLogics) == 0) {
            echo '<br><div class="text-center" style="font-size:1.2em;font-weight:bold;">{{Aucun équipement Template trouvé, cliquer sur "Ajouter" pour commencer}}</div>';
        } else {
            // Champ de recherche
            echo '<div class="input-group" style="margin:5px;">';
            echo '<input class="form-control roundedLeft" placeholder="{{Rechercher}}" id="in_searchEqlogic">';
            echo '<div class="input-group-btn">';
            echo '<a id="bt_resetSearch" class="btn" style="width:30px"><i class="fas fa-times"></i></a>';
            echo '<a class="btn roundedRight hidden" id="bt_pluginDisplayAsTable" data-coreSupport="1" data-state="0"><i class="fas fa-grip-lines"></i></a>';
            echo '</div>';
            echo '</div>';
            // Liste des équipements du plugin
            echo '<div class="eqLogicThumbnailContainer">';
            foreach ($eqLogics as $eqLogic) {
                $opacity = ($eqLogic->getIsEnable()) ? '' : 'disableCard';
                echo '<div class="eqLogicDisplayCard cursor '.$opacity.'" data-eqLogic_id="' . $eqLogic->getId() . '">';
                echo '<img src="' . $plugin->getPathImgIcon() . '">';
                echo '<br>';
                echo '<span class="name">' . $eqLogic->getHumanName(true, true) . '</span>';
                echo '<span class="hiddenAsCard displayTableRight hidden">';
                echo ($eqLogic->getIsVisible() == 1) ? '<i class="fas fa-eye" title="{{Equipement visible}}"></i>' : '<i class="fas fa-eye-slash" title="{{Equipement non visible}}"></i>';
                echo '</span>';
                echo '</div>';
            }
            echo '</div>';
        }
        ?>
    </div> <!-- /.eqLogicThumbnailDisplay -->

    <!-- Page de présentation de l'équipement -->
    <div class="col-xs-12 eqLogic" style="display: none;">
        <!-- barre de gestion de l'équipement -->
        <div class="input-group pull-right" style="display:inline-flex;">
            <span class="input-group-btn">
                <!-- Les balises <a></a> sont volontairement fermées à la ligne suivante pour éviter les espaces entre les boutons. Ne pas modifier -->
                <a class="btn btn-sm btn-default eqLogicAction roundedLeft" data-action="configure"><i class="fas fa-cogs"></i><span class="hidden-xs"> {{Configuration avancée}}</span>
                </a><a class="btn btn-sm btn-default eqLogicAction" data-action="copy"><i class="fas fa-copy"></i><span class="hidden-xs">  {{Dupliquer}}</span>
                </a><a class="btn btn-sm btn-success eqLogicAction" data-action="save"><i class="fas fa-check-circle"></i> {{Sauvegarder}}
                </a><a class="btn btn-sm btn-danger eqLogicAction roundedRight" data-action="remove"><i class="fas fa-minus-circle"></i> {{Supprimer}}
                </a>
            </span>
        </div>
        <!-- Onglets -->
        <ul class="nav nav-tabs" role="tablist">
            <li role="presentation"><a href="#" class="eqLogicAction" aria-controls="home" role="tab" data-toggle="tab" data-action="returnToThumbnailDisplay"><i class="fas fa-arrow-circle-left"></i></a></li>
            <li role="presentation" class="active"><a href="#eqlogictab" aria-controls="home" role="tab" data-toggle="tab"><i class="fas fa-tachometer-alt"></i> {{Equipement}}</a></li>
            <li role="presentation"><a href="#commandtab" aria-controls="home" role="tab" data-toggle="tab"><i class="fas fa-list"></i> {{Commandes}}</a></li>
            <li role="presentation"><a href="#scheduletab" aria-controls="home" role="tab" data-toggle="tab"><i class="fas fa-clock"></i> {{Schedules}}</a></li>
        </ul>
        <div class="tab-content">
            <!-- Onglet de configuration de l'équipement -->
            <div role="tabpanel" class="tab-pane active" id="eqlogictab">
                <!-- Partie gauche de l'onglet "Equipements" -->
                <!-- Paramètres généraux et spécifiques de l'équipement -->
                <form class="form-horizontal">
                    <fieldset>
                        <div class="col-lg-6">
                            <legend><i class="fas fa-wrench"></i> {{Paramètres généraux}}</legend>
                            <div class="form-group">
                                <label class="col-sm-4 control-label">{{Nom de l'équipement}}</label>
                                <div class="col-sm-6">
                                    <input type="text" class="eqLogicAttr form-control" data-l1key="id" style="display:none;">
                                    <input type="text" class="eqLogicAttr form-control" data-l1key="name" placeholder="{{Nom de l'équipement}}">
                                </div>
                            </div>
                            <div class="form-group">
                                <label class="col-sm-4 control-label" >{{Objet parent}}</label>
                                <div class="col-sm-6">
                                    <select id="sel_object" class="eqLogicAttr form-control" data-l1key="object_id">
                                        <option value="">{{Aucun}}</option>
                                        <?php
                                        $options = '';
                                        foreach ((jeeObject::buildTree(null, false)) as $object) {
                                            $options .= '<option value="' . $object->getId() . '">' . str_repeat('&nbsp;&nbsp;', $object->getConfiguration('parentNumber')) . $object->getName() . '</option>';
                                        }
                                        echo $options;
                                        ?>
                                    </select>
                                </div>
                            </div>
                            <div class="form-group">
                                <label class="col-sm-4 control-label">{{Catégorie}}</label>
                                <div class="col-sm-6">
                                    <?php
                                    foreach (jeedom::getConfiguration('eqLogic:category') as $key => $value) {
                                        echo '<label class="checkbox-inline">';
                                        echo '<input type="checkbox" class="eqLogicAttr" data-l1key="category" data-l2key="' . $key . '" >' . $value['name'];
                                        echo '</label>';
                                    }
                                    ?>
                                </div>
                            </div>
                            <div class="form-group">
                                <label class="col-sm-4 control-label">{{Options}}</label>
                                <div class="col-sm-6">
                                    <label class="checkbox-inline"><input type="checkbox" class="eqLogicAttr" data-l1key="isEnable" checked>{{Activer}}</label>
                                    <label class="checkbox-inline"><input type="checkbox" class="eqLogicAttr" data-l1key="isVisible" checked>{{Visible}}</label>
                                </div>
                            </div>

                            <legend><i class="fas fa-cogs"></i> {{Pamètres spécifiques}}</legend>
                            <div class="form-group">
                                <label class="col-sm-4 control-label">{{Zones}}</label>
                                <div class="col-sm-6">
                                    <table id="table_zones" class="table table-bordered table-condensed">
                                        <thead>
                                            <tr>
                                                <th class="hidden-xs" style="width:40px;">ID</th>
                                                <th>{{Nom}}</th>
                                                <th style="width:40px">{{Actif}}</th>
                                                <th style="width:50px">{{Action}}</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                        </tbody>
                                    </table>
                                    <a class="btn btn-sm btn-success zoneAction pull-right" data-action="add"><i class="fas fa-plus-circle"></i> {{Ajouter une zone}}</a>
                                </div>
                            </div>
                        </div>

                        <!-- Partie droite de l'onglet "Équipement" -->
                        <!-- Affiche un champ de commentaire par défaut mais vous pouvez y mettre ce que vous voulez -->
                        <div class="col-lg-6">
                            <legend><i class="fas fa-info"></i> {{Informations}}</legend>
                            <div class="form-group">
                                <label class="col-sm-4 control-label">{{Description}}</label>
                                <div class="col-sm-6">
                                    <textarea class="form-control eqLogicAttr autogrow" data-l1key="comment"></textarea>
                                </div>
                            </div>
                        </div>
                    </fieldset>
                </form>
            </div><!-- /.tabpanel #eqlogictab-->

            <!-- Onglet des commandes de l'équipement -->
            <div role="tabpanel" class="tab-pane" id="commandtab">
                <a class="btn btn-default btn-sm pull-right cmdAction" data-action="add-info" style="margin-top:5px;"><i class="fas fa-plus-circle"></i> {{Ajouter une commande}}</a>
                <br><br>
                <div class="table-responsive">
                    <table id="table_cmd" class="table table-bordered table-condensed">
                        <thead>
                            <tr>
                                <th class="hidden-xs" style="min-width:50px;width:70px;">ID</th>
                                <th style="min-width:150px;width:300px;">{{Nom}}</th>
                                <th style="width:130px;">{{Type}}</th>
                                <th style="width:110px;">{{LogicalId}}</th>
                                <th style="min-width:180px;">{{Valeur}}</th>
                                <th style="width:160px">{{Zone}}</th>
                                <th style="min-width:260px;width:310px">{{Options}}</th>
                                <th>{{Etat}}</th>
                                <th style="min-width:80px;width:140px;">{{Actions}}</th>
                            </tr>
                        </thead>
                        <tbody>
                        </tbody>
                    </table>
                </div>
            </div><!-- /.tabpanel #commandtab-->

            <!-- Onglet des schedules -->
            <div role="tabpanel" class="tab-pane" id="scheduletab">
		<div class="row"> <!-- La ligne des légendes -->
                    <div class="col-sm-6"> <!-- moitié gauche du panel -->
                        <legend><i class="fas fa-tachometer-alt"></i> {{Consignes}}</legend>
			<div class="col-sm-6">
                            <form class="form-vertical">
                                <fieldset>
                                    <label class="control-label">{{Vue}}:
                                        <div class="form-group">
                                            <label class="radio-inline control-label" for="selectvue-jour">
                                               <input id="selectvue-jour" type="radio" name="selectvue" value="jour" checked/>
                                                Jour
                                            </label>
                                            <label class="radio-inline control-label" for="selectvue-zone">
                                                <input id="selectvue-zone" type="radio" name="selectvue" value="zone"/>
                                                Zone
                                            </label>
                                        </div>
                                    </label>
                                </fieldset>
                            </form>
                        </div>
			<div class="col-sm-6">
                            <form class="form-vertical">
                                <fieldset>
                                    <label id="selectionJour" class="control-label">{{Jour}}:
                                        <select id="selectJour" class="form-control">
                                            <option value='1'>{{lundi}}</option>
                                            <option value='2'>{{mardi}}</option>
                                            <option value='3'>{{mercredi}}</option>
                                            <option value='4'>{{jeudi}}</option>
                                            <option value='5'>{{vendredi}}</option>
                                            <option value='6'>{{samedi}}</option>
                                            <option value='7'>{{dimanche}}</option>
                                        </select>
                                    </label>
                                    <label id="selectionZone" class="control-label hidden">{{Zone}}:
                                        <select id="selectZone" class="form-control zoneSelector">
                                        </select>
                                    </label>
                                </fieldset>
                            </form>
                        </div>
                    </div>
                    <div class="col-sm-6"> <!-- moitié droite du panel -->
			<legend><i class="fas fa-calendar-times"></i> {{Absences}}</legend>
                    </div>
		</div> <!-- La ligne des légendes -->
                <div class="col-sm-6"> <!-- moitié gauche du panel -->
                   <div class="col-sm-6">
                   </div>
                   <div class="form-group col-sm-6">
                   </div>
		</div>
                <div class="form-group col-sm-6"> <!-- moitié droite du panel -->
                   <div class="input-group pull-right" style="display:inline-flex;top:-110px">
                       <span class="input-group-btn">
                           <a class="btn btn-sm btn-default absenceAction roundedLeft" data-action="add">
                               <i class="fas fa-plus-circle"></i>
                               <span class="hidden-xs"> {{Ajouter une absence}}</span>
                           </a>
                       </span>
                   </div>
		</div>

                <div class="form-group col-sm-6"> <!-- moitié droite du panel -->
                   <div class="table-responsive col-sm-12">
                       <table id="table_schedules" class="table table-bordered table-condensed">
                           <thead>
                           </thead>
                           <tbody>
                           </tbody>
                       </table>
                   </div>
               </div> <!-- moitié gauche du panel -->
                   <table id="table_absences" class="table table-bordered table-condensed">
                        <thead>
                            <tr>
                                <th>{{Du}}</th>
                                <th>{{Au}}</th>
				<th/>
                            </tr>
                        </thead>
                        <tbody>
                        </tbody>
                   </table>
                   <script>
                        $(function() {
                            $('#datepicker').datetimepicker()
                        })
                   </script>
               </div> <!-- moitié droite du panel -->
            </div><!-- /.tabpanel #scheduletab-->

        </div><!-- /.tab-content -->
    </div><!-- /.eqLogic -->
</div><!-- /.row row-overflow -->

<!-- Inclusion du fichier javascript du plugin (dossier, nom_du_fichier, extension_du_fichier, id_du_plugin) -->
<?php include_file('desktop', 'chauffage', 'js', 'chauffage');?>
<?php include_file('desktop', 'chauffage', 'css', 'chauffage');?>
<!-- Inclusion du fichier javascript du core - NE PAS MODIFIER NI SUPPRIMER -->
<?php include_file('core', 'plugin.template', 'js');?>
