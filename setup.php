<?php
global $CFG_GLPI;
// Version of the plugin
define('PLUGIN_FORMCREATOR_VERSION', "0.90-1.4-beta3");
// Minimal GLPI version, inclusive
define ("PLUGIN_FORMCREATOR_GLPI_MIN_VERSION", "0.85");
// Maximum GLPI version, exclusive
define ("PLUGIN_FORMCREATOR_GLPI_MAX_VERSION", "0.91");

define('FORMCREATOR_ROOTDOC', $CFG_GLPI['root_doc']."/plugins/formcreator");

/**
 * Define the plugin's version and informations
 *
 * @return Array [name, version, author, homepage, license, minGlpiVersion]
 */
function plugin_version_formcreator ()
{
   return array(
      'name'           => _n('Form', 'Forms', 2, 'formcreator'),
      'version'        => PLUGIN_FORMCREATOR_VERSION,
      'author'         => '<a href="mailto:contact@teclib.com">Jérémy MOREAU</a>
                           - <a href="http://www.teclib.com">Teclib\'</a>',
      'homepage'       => 'https://github.com/pluginsGLPI/formcreator',
      'license'        => '<a href="../plugins/formcreator/LICENSE" target="_blank">GPLv2</a>',
      'minGlpiVersion' => PLUGIN_FORMCREATOR_GLPI_MIN_VERSION
   );
}

/**
 * Check plugin's prerequisites before installation
 *
 * @return boolean
 */
function plugin_formcreator_check_prerequisites ()
{
   $success = true;
   if (version_compare(GLPI_VERSION, PLUGIN_FORMCREATOR_GLPI_MIN_VERSION, 'lt')) {
      echo 'This plugin requires GLPI >= ' . PLUGIN_FORMCREATOR_GLPI_MIN_VERSION . '<br>';
      $success = false;
   }
   if (! function_exists("utf8_decode")) {
      echo "This plugin requires php-xml<br>";
      $success = false;
   }

   return $success;
}

/**
 * Check plugin's config before activation (if needed)
 *
 * @param string $verbose Set true to show all messages (false by default)
 * @return boolean
 */
function plugin_formcreator_check_config($verbose=false)
{
   return true;
}

/**
 * Initialize all classes and generic variables of the plugin
 */
function plugin_init_formcreator ()
{
   global $PLUGIN_HOOKS, $CFG_GLPI, $DB;

   // Hack for vertical display
   if (isset($CFG_GLPI['layout_excluded_pages'])) {
      array_push($CFG_GLPI['layout_excluded_pages'], "targetticket.form.php");
   }

   // Set the plugin CSRF compliance (required since GLPI 0.84)
   $PLUGIN_HOOKS['csrf_compliant']['formcreator'] = true;

   // Can assign FormAnswer to tickets
   $PLUGIN_HOOKS['assign_to_ticket']['formcreator'] = true;
   array_push($CFG_GLPI["ticket_types"], 'PluginFormcreatorFormanswer');
   array_push($CFG_GLPI["document_types"], 'PluginFormcreatorFormanswer');

   $plugin = new Plugin();
   if ($plugin->isInstalled('formcreator') && $plugin->isActivated('formcreator')) {

      // Redirect to helpdesk replacement
      if (strpos($_SERVER['REQUEST_URI'], "front/helpdesk.public.php") !== false) {
         if (!isset($_POST['newprofile']) && !isset($_GET['active_entity'])) {
            // Not changing profile or active entity
            if (isset($_SESSION['glpiactiveprofile']['interface'])
                  && isset($_SESSION['glpiactive_entity'])) {
               // Interface and active entity are set in session
               if (plugin_formcreator_replaceHelpdesk()) {
                  Html::redirect($CFG_GLPI["root_doc"]."/plugins/formcreator/front/wizard.php");
               }
            }
         }
      }

      // Massive Action definition
      $PLUGIN_HOOKS['use_massive_action']['formcreator'] = 1;

      // Load menu entries if user is logged in and if he has access to at least one form
      if (isset($_SESSION['glpiID'])) {
         // If user have acces to one form or more, add link
         $form_table = getTableForItemType('PluginFormcreatorForm');
         $table_fp   = getTableForItemType('PluginFormcreatorFormprofiles');
         $where      = getEntitiesRestrictRequest( "", $form_table, "", "", true, false);
         $query      = "SELECT COUNT($form_table.id)
                        FROM $form_table
                        WHERE $form_table.`is_active` = 1
                        AND $form_table.`is_deleted` = 0
                        AND ($form_table.`language` = '{$_SESSION['glpilanguage']}'
                             OR $form_table.`language` IN ('0', '', NULL))
                        AND $where
                        AND ($form_table.`access_rights` != " . PluginFormcreatorForm::ACCESS_RESTRICTED . " OR $form_table.`id` IN (
                           SELECT plugin_formcreator_forms_id
                           FROM $table_fp
                           WHERE plugin_formcreator_profiles_id = " . $_SESSION['glpiactiveprofile']['id']."))";
         $result = $DB->query($query);
         list($nb) = $DB->fetch_array($result);
         if ($nb > 0) {
            $PLUGIN_HOOKS['menu_toadd']['formcreator']['helpdesk'] = 'PluginFormcreatorFormlist';
         }

         // Add a link in the main menu plugins for technician and admin panel
         $PLUGIN_HOOKS['menu_entry']['formcreator'] = 'front/formlist.php';

         // Config page
         $links  = array();
         if (Session::haveRight('entity', UPDATE)) {
            $PLUGIN_HOOKS['config_page']['formcreator']         = 'front/form.php';
            $PLUGIN_HOOKS['menu_toadd']['formcreator']['admin'] = 'PluginFormcreatorForm';
            $links['config'] = '/plugins/formcreator/front/form.php';
            $links['add']    = '/plugins/formcreator/front/form.form.php';
         }
         $img = '<img  src="' . $CFG_GLPI['root_doc'] . '/plugins/formcreator/pics/check.png"
                     title="' . __('Forms waiting for validation', 'formcreator') . '" alt="Waiting forms list" />';

         $links[$img] = '/plugins/formcreator/front/formanswer.php';

         // Set options for pages (title, links, buttons...)
         $links['search'] = '/plugins/formcreator/front/formlist.php';
         $PLUGIN_HOOKS['submenu_entry']['formcreator']['options'] = array(
            'config'       => array('title'  => __('Setup'),
                                    'page'   => '/plugins/formcreator/front/form.php',
                                    'links'  => $links),
            'options'      => array('title'  => _n('Form', 'Forms', 2, 'formcreator'),
                                    'links'  => $links),
         );
      }

      // Load JS and CSS files if we are on a page witch need them
      if (strpos($_SERVER['REQUEST_URI'], "plugins/formcreator") !== false
          || strpos($_SERVER['REQUEST_URI'], "central.php") !== false
          || isset($_SESSION['glpiactiveprofile']) &&
             $_SESSION['glpiactiveprofile']['interface'] == 'helpdesk') {

          // Add specific CSS
         $PLUGIN_HOOKS['add_css']['formcreator'][] = "css/styles.css";

         $PLUGIN_HOOKS['add_css']['formcreator'][]        = 'lib/pqselect/pqselect.min.css';
         $PLUGIN_HOOKS['add_javascript']['formcreator'][] = 'lib/pqselect/pqselect.min.js';

         // Add specific JavaScript
         $PLUGIN_HOOKS['add_javascript']['formcreator'][] = 'scripts/forms-validation.js.php';
         $PLUGIN_HOOKS['add_javascript']['formcreator'][] = 'scripts/scripts.js.php';
      }

      if (strpos($_SERVER['REQUEST_URI'], "helpdesk") !== false
            || strpos($_SERVER['REQUEST_URI'], "central.php") !== false
            || strpos($_SERVER['REQUEST_URI'], "formcreator/front/formlist.php") !== false
            || strpos($_SERVER['REQUEST_URI'], "formcreator/front/wizard.php") !== false) {
         $PLUGIN_HOOKS['add_javascript']['formcreator'][] = 'lib/slinky/assets/js/jquery.slinky.js';
         $PLUGIN_HOOKS['add_css']['formcreator'][]        = 'lib/slinky/assets/css/jquery.slinky.css';

         $PLUGIN_HOOKS['add_javascript']['formcreator'][] = 'lib/masonry.pkgd.min.js';
      }

      // Load field class and all its method to manage fields
      Plugin::registerClass('PluginFormcreatorFields');

      // Notification
      Plugin::registerClass('PluginFormcreatorFormanswer', array(
         'notificationtemplates_types' => true
      ));

      Plugin::registerClass('PluginFormcreatorEntityconfig', array('addtabon' => 'Entity'));
   }
}

/**
 * Encode special chars
 *
 * @param  String    $string  The string to encode
 * @return String             The encoded string
 */
function plugin_formcreator_encode($string, $mode_legacy=true)
{
   if (!$mode_legacy) {
      $string = Html::clean(Html::entity_decode_deep($string));
      $string = preg_replace('/\\r\\n/',' ',$string);
      $string = preg_replace('/\\n/',' ',$string);
      $string = preg_replace('/\\\\r\\\\n/',' ',$string);
      $string = preg_replace('/\\\\n/',' ',$string);
      $string = Toolbox::stripslashes_deep($string);
      $string = Toolbox::addslashes_deep($string);
   } else {
      $string = stripcslashes($string);
      $string = html_entity_decode($string, ENT_QUOTES, 'UTF-8');
      $string = str_replace('&apos;', "'", $string);
      $string = htmlentities($string, ENT_QUOTES, 'UTF-8');
   }
   return $string;
}

/**
 * Encode special chars
 *
 * @param  String    $string  The string to encode
 * @return String             The encoded string
 */
function plugin_formcreator_decode($string)
{
   $string = stripcslashes($string);
   $string = html_entity_decode($string, ENT_QUOTES, 'UTF-8');
   $string = str_replace('&apos;', "'", $string);
   return $string;
}

/**
 * Tells if helpdesk replacement is enabled for the current user
 */
function plugin_formcreator_replaceHelpdesk() {
   if (isset($_SESSION['glpiactiveprofile']['interface'])
         && isset($_SESSION['glpiactive_entity'])) {
      // Interface and active entity are set in session
      if (PluginFormcreatorEntityconfig::getUsedConfig('replace_helpdesk', $_SESSION['glpiactive_entity']) == '1'
            && $_SESSION['glpiactiveprofile']['interface'] == 'helpdesk') {
         return true;
      }
   }
   return false;
}
