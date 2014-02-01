<?php

class modules_related_field_widget {

    /**
     * @brief The base URL to the depselect module.  This will be correct whether it is in the 
     * application modules directory or the xataface modules directory.
     *
     * @see getBaseURL()
     */
    private $baseURL = null;
    private $pathsRegistered = false;

    /**
     * @brief Returns the base URL to this module's directory.  Useful for including
     * Javascripts and CSS.
     *
     */
    public function getBaseURL() {
        if (!isset($this->baseURL)) {
            $this->baseURL = Dataface_ModuleTool::getInstance()->getModuleURL(__FILE__);
        }
        return $this->baseURL;
    }

    public function registerPaths() {
        if (!$this->pathsRegistered) {
            $this->pathsRegistered = true;

            //df_register_skin('modules_related_field_widget', dirname(__FILE__).'/templates');
            Dataface_JavascriptTool::getInstance()
                    ->addPath(
                            dirname(__FILE__) . '/js', $this->getBaseURL() . '/js'
            );

            //Dataface_CSSTool::getInstance()
            //        ->addPath(
            //                dirname(__FILE__) . '/css', $this->getBaseURL() . '/css'
            //);
        }
    }

    public function __construct() {
        $app = Dataface_Application::getInstance();

        // Register the beforeSave event handler to be called before any records
        // in the system are saved.
        $app->registerEventListener('beforeSave', array($this, 'beforeSave'));

        // Register the afterSave event handler to be called after any records
        // are saved.
        $app->registerEventListener('afterSave', array($this, 'afterSave'));

        // Register the initTransientField event handler which is called 
        // when transient field data is loaded for the first time.
        $app->registerEventListener('initTransientField', array($this, 'initField'));

        // Now work on our dependencies
        $mt = Dataface_ModuleTool::getInstance();

        // We require the XataJax module
        // The XataJax module activates and embeds the Javascript and CSS tools
        $mt->loadModule('modules_XataJax', 'modules/XataJax/XataJax.php');


        // Register the tagger widget with the form tool so that it responds
        // to widget:type=tagger
        import('Dataface/FormTool.php');
        $ft = Dataface_FormTool::getInstance();
        $ft->registerWidgetHandler('tagger', dirname(__FILE__) . '/widget.php', 'Dataface_FormTool_tagger');
    }

    public function beforeSave(stdClass $event) {
        
    }

    public function afterSave(stdClass $event) {
        
    }

    public function initField(stdClass $event) {
        if (@$event->field['widget']['lookup_field']) {
            $fld = & $event->field;
            $lookupFieldName = $fld['widget']['lookup_field'];
            $lookupField = & $event->record->table()->getField($lookupFieldName);
            if (PEAR::isError($lookupFieldName)) {
                error_log("Failed to find lookup field " . $lookupFieldName . " when parsing related_field_widget for " . $fld['name']);
                return;
            }

            $currentLookupValue = $event->record->val($lookupFieldName);

            if (!$currentLookupValue) {
                // There is no lookup value currently so we won't load the value here
                // either.
                return;
            }
            $remoteFieldName = $fld['name'];
            if (isset($fld['widget']['remote_field'])) {
                $remoteFieldName = $fld['widget']['remote_field'];
            }

            if (!@$lookupField['widget']['table']) {
                error_log("Lookup field " . $lookupField['name'] . " does not specify widget:table value when parsing related_field_widget for " . $fld['name']);
                return;
            }

            $remoteTable = Dataface_Table::loadTable($lookupField['widget']['table']);
            if (PEAR::isError($remoteTable)) {
                error_log("Failed to load remote table " . $lookupField['widget']['table'] . " while parsing related_field_widget " . $fld['name']);
                return;
            }
            $remoteField = & $remoteTable->getField($remoteFieldName);
            if (PEAR::isError($remoteField)) {
                error_log("Failed to find remote field " . $remoteFieldName . " when parsing related_field_widget for " . $fld['name']);
                return;
            }

            $pkeys = array_keys($remoteTable->keys());
            if (count($pkeys) > 0) {
                error_log("related_field_widget only works when the remote table has a single column primary key.  Compound primary keys not supported.  While parsing related_field_widget for " . $fld['name']);
                return;
            }

            $keyName = $pkeys[0];
            $query = array($keyName => '=' . $currentLookupValue);

            $remoteRecord = df_get_record($remoteTable->tablename, $query);
            if (!$remoteRecord or PEAR::isError($remoteRecord)) {
                error_log("Failed to parse_related_widget for " . $fld['name'] . " because the remote record with ID " . $currentLookupValue . " could not be found.");
                return;
            }


            return $remoteRecord->val($remoteFieldName);
        }
    }

    /**
     * @brief Fills the after_form_open_tag block to add  javascripts to the form
     * appropriately.
     *
     * @param array $params An associative array of the Smarty tag parameters.  This
     * block expects at least the following data structure:
     * @code
     * array(
     *     'form' => <array> // The form data structure as passed to the Dataface_Form_Template.html template
     * )
     * @endcode
     */
    function block__after_form_open_tag($params = array()) {

        $form = $params['form'];
        if (!$this->formRequiresRelatedFieldWidget($form)) {
            return null;
        }

        $this->registerPaths();
        
        $metadata = array();
        foreach ( $form['elements'] as $e ){
            if (@$e['field']['transient'] and @$e['field']['widget']['lookup_field'] ) {
                $o = array(
                    'name' => $e['field']['name'],
                    'lookup_field' => $e['field']['lookup_field']
                
                );
                
                $remoteFieldName = $e['field']['name'];
                if (isset($e['field']['widget']['remote_field'])) {
                    $remoteFieldName = $e['field']['widget']['remote_field'];
                }
                $o['remote_field'] = $remoteFieldName;
                $metadata[$e['field']['name']] = $o;
            }
            $json = json_encode($metadata);
            echo '<script>'.df_escape('Xataface_modules_related_field_widget_metadata='.$json.';').'</script>';
        }

        //$ct = Dataface_CSSTool::getInstance();
        //$ct->addPath(dirname(__FILE__) . '/css', $this->getBaseURL() . '/css');

        // Add our javascript
        $jt->import('xataface/modules/related_field_widget/related_field_widget.js');
        
    }

    function formRequiresRelatedFieldWidget($form) {
        if (@$form['elements'] and is_array($form['elements'])) {
            foreach ($form['elements'] as $e) {
                if (@$e['field']['transient'] and @$e['field']['widget']['lookup_field'] ) {
                    return true;
                }
            }
        }

        if (@$form['sections'] and is_array($form['sections'])) {
            foreach ($form['sections'] as $s) {
                if ($s['elements'] and is_array($s['elements'])) {
                    foreach ($s['elements'] as $e) {
                        if (@$e['field']['transient'] and @$e['field']['widget']['lookup_field'] ) {
                            return true;
                        }
                    }
                }
            }
        }

        return false;
    }

}
