<?php

class PluginFormcreatorFormprofiles extends CommonDBRelation
{
   static public $itemtype_1 = 'PluginFormcreatorForm';
   static public $items_id_1 = 'plugin_formcreator_forms_id';
   static public $itemtype_2 = 'Profile';
   static public $items_id_2 = 'plugin_formcreator_profiles_id';

   static function getTypeName($nb=0)
   {
      return _n('Target', 'Targets', $nb, 'formcreator');
   }

   function getTabNameForItem(CommonGLPI $item, $withtemplate=0)
   {
         return self::getTypeName(2);
   }

   static function displayTabContentForItem(CommonGLPI $item, $tabnum=1, $withtemplate=0)
   {
      global $DB, $CFG_GLPI;

      echo "<form name='notificationtargets_form' id='notificationtargets_form'
             method='post' action=' ";
      echo Toolbox::getItemTypeFormURL(__CLASS__)."'>";
      echo "<table class    ='tab_cadre_fixe'>";

      echo '<tr><th colspan="2">'.__('Access type', 'formcreator').'</th>';
      echo '</tr>';
      echo '<td>';
      Dropdown::showFromArray(
         'access_rights',
         array(
            PluginFormcreatorForm::ACCESS_PUBLIC     => __('Public access', 'formcreator'),
            PluginFormcreatorForm::ACCESS_PRIVATE    => __('Private access', 'formcreator'),
            PluginFormcreatorForm::ACCESS_RESTRICTED => __('Restricted access', 'formcreator'),
         ),
         array(
            'value' => (isset($item->fields["access_rights"])) ? $item->fields["access_rights"] : 1,
         )
      );
      echo '</td>';
      echo '<td>'.__('Link to the form', 'formcreator').': ';
      if ($item->fields['is_active']) {
         $form_url = $CFG_GLPI['url_base'].'/plugins/formcreator/front/formdisplay.php?id='.$item->getID();
         echo '<a href="'.$form_url.'">'.$form_url.'</a>&nbsp;';
         echo '<a href="mailto:?subject='.$item->getName().'&body='.$form_url.'" target="_blank">';
         echo '<img src="'.$CFG_GLPI['root_doc'].'/plugins/formcreator/pics/email.png" />';
         echo '</a>';
      } else {
         echo __('Please active the form to view the link', 'formcreator');
      }
      echo '</td>';
      echo "</tr>";

      if ($item->fields["access_rights"] == PluginFormcreatorForm::ACCESS_RESTRICTED) {
         echo '<tr><th colspan="2">'.self::getTypeName(2).'</th></tr>';

         $table         = getTableForItemType(__CLASS__);
         $table_profile = getTableForItemType('Profile');
         $query = "SELECT p.`id`, p.`name`, IF(f.`plugin_formcreator_profiles_id` IS NOT NULL, 1, 0) AS `profile`
                   FROM $table_profile p
                   LEFT JOIN $table f
                     ON p.`id` = f.`plugin_formcreator_profiles_id`
                     AND f.`plugin_formcreator_forms_id` = ".$item->fields['id'];
         $result = $DB->query($query);
         while(list($id, $name, $profile) = $DB->fetch_array($result)) {
            $checked = $profile ? ' checked' : '';
            echo '<tr><td colspan="2"><label>';
            echo '<input type="checkbox" name="profiles_id[]" value="'.$id.'" '.$checked.'> ';
            echo $name;
            echo '</label></td></tr>';
         }
      }

      echo '<tr>';
         echo '<td class="center" colspan="2">';
            echo '<input type="hidden" name="profiles_id[]" value="0" />';
            echo '<input type="hidden" name="form_id" value="'.$item->fields['id'].'" />';
            echo '<input type="submit" name="update" value="'.__('Save').'" class="submit" />';
         echo "</td>";
      echo "</tr>";

      echo "</table>";
      Html::closeForm();
   }

   static function install(Migration $migration)
   {
      global $DB;

      $table = getTableForItemType(__CLASS__);
      if (!TableExists($table)) {
         $query = "CREATE TABLE IF NOT EXISTS `$table` (
                     `plugin_formcreator_forms_id` INT NOT NULL ,
                     `plugin_formcreator_profiles_id` INT NOT NULL ,
                     PRIMARY KEY (`plugin_formcreator_forms_id`, `plugin_formcreator_profiles_id`)
                  ) ENGINE=MyISAM  DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci";
         $DB->query($query) or die($DB->error());
      }

      return true;
   }

   static function uninstall()
   {
      global $DB;

      $query = "DROP TABLE IF EXISTS `".getTableForItemType(__CLASS__)."`";
      return $DB->query($query) or die($DB->error());
   }
}
