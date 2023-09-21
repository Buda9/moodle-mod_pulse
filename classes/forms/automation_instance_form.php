<?php


namespace mod_pulse\forms;

defined('MOODLE_INTERNAL') || die('No direct access!');

require_once($CFG->dirroot.'/lib/formslib.php');

use html_writer;
use mod_pulse\automation\templates;

// Define the automation template form class by extending moodleform.
class automation_instance_form extends automation_template_form {


    public function after_definition() {
        global $PAGE;

        // parent::definition();

        $mform =& $this->_form;

        $mform->updateAttributes(['id' => 'pulse-automation-template' ]);

        $course = $this->_customdata['courseid'] ?? '';
        $mform->addElement('hidden', 'courseid', $course);
        $mform->setType('courseid', PARAM_INT);

        $templateid = $this->_customdata['templateid'] ?? '';
        $mform->addElement('hidden', 'templateid', $templateid);
        $mform->setType('templateid', PARAM_INT);

        $templateid = $this->_customdata['instanceid'] ?? '';
        $mform->addElement('hidden', 'instanceid', $templateid);
        $mform->setType('instanceid', PARAM_INT);

        $mform->removeElement('visible');
        $mform->removeElement('categories');

        // Get the list of elments add in this form. create override button for all elements expect the hidden elements.
        $elements = $mform->_elements;

        // print_object($elements);exit;
        // Add the Reference element.
        $reference = $mform->createElement('text', 'insreference', get_string('reference', 'pulse'), ['size' => '50']);
        $mform->insertElementBefore($reference, 'reference');
        $mform->setType('insreference', PARAM_ALPHANUMEXT);
        /* $mform->addRule('insreference', null, 'required', null, 'client');
        $mform->addHelpButton('insreference', 'reference', 'pulse'); */

        $mform->removeElement('reference');

        $templatereference = $this->get_customdata('templatereference');
        $input = html_writer::empty_tag('input', ['class' => 'form-control', 'type' => 'text', 'value' => $templatereference, 'disabled' => 'disabled']);
        $referenceprefix = $mform->createElement('html', html_writer::div($input, 'hide', ['id' => 'pulse-template-reference']));
        $mform->insertElementBefore($referenceprefix, 'insreference');

        $this->load_default_override_elements(['insreference']);

        if (!empty($elements)) {
            // List of element type don't need to add the override option.
            $dontoverride = ['html', 'header', 'hidden', 'button'];

            foreach ($elements as $element) {

                if (!in_array($element->getType(), $dontoverride) && $element->getName() !== 'buttonar') {
                    $this->add_override_element($element);
                }
            }
        }

        // Return to the required element tab on submit.
        // $PAGE->requires->js_call_amd('mod_pulse/automation', 'init');

    }

    protected function add_override_element($element) {
        $mform =& $this->_form;

        $elementname = $element->getName();
        $orgelementname = $elementname;

        if (stripos($elementname, "[") !== false) {
            $name = str_replace("]", "", str_replace("[", "_", $elementname));
            $name = 'override[' . $name .']';
        } else {
            $name = 'override[' . $elementname .']';
        }

        // Override element already exists, no need to create new one.
        if (isset($mform->_elementIndex[$name])) {
            return;
        }

        $overrideelement = $mform->createElement('advcheckbox', $name, '', '', array('group' => 'automation', 'class' => 'custom-control-input'), array(0, 1));

        // Insert the override checkbox before the element.
        if (isset($mform->_elementIndex[$orgelementname]) && $mform->_elementIndex[$orgelementname]) {
            $mform->insertElementBefore($overrideelement, $orgelementname);
        }

        // Disable the form fields by default, only enable whens its enabled for overriddden.
        // if (!isset($mform->_rules[$element->getName()]) || empty($mform->_rules[$element->getName()])) {
            $mform->disabledIf($orgelementname, $name, 'notchecked');
     /*   // }  else {
            $element = $mform->getElement($orgelementname);
            $element->updateAttributes(["readonly" => 'readonly']);
        } */
    }

    /**
     * Includ the template action trigger element to the templates form.
     *
     * @param [type] $mform
     * @return void
     */
    protected function load_template_conditions() {

        $mform =& $this->_form;

        $mform->addElement('html', '<div class="tab-pane fade" id="pulse-condition-tab"> ');

        $mform->addElement('header', 'generalconditions', '<h3>'.get_string('general').'</h3>');

        // Operator element.
        $operators = [
            \mod_pulse\automation\action_base::OPERATOR_ALL => get_string('all', 'pulse'),
            \mod_pulse\automation\action_base::OPERATOR_ANY => get_string('any', 'pulse'),
        ];
        $mform->addElement('select', 'triggeroperator', get_string('triggeroperator', 'pulse'), $operators);
        $mform->addHelpButton('triggeroperator', 'triggeroperator', 'pulse');

        $conditionplugins = new \mod_pulse\plugininfo\pulsecondition();
        $plugins = $conditionplugins->get_plugins_base();

        $option = [];
        foreach ($plugins as $name => $plugin) {
            $mform->addElement('header', $name, get_string('pluginname', 'pulsecondition_'.$name));

            $plugin->load_instance_form($mform, $this);
            $plugin->upcoming_element($mform);
            $mform->setExpanded($name);
        }
        $mform->addElement('html', '</fieldset>'); // E.o of actions triggere tab.

        $mform->addElement('html', html_writer::end_div()); // E.o of actions triggere tab.

    }


    /**
     * Load template actions.
     *
     * @param [type] $mform
     * @return void
     */
    protected function load_template_actions(&$mform) {

        // $mform->addElement('html', '<div class="tab-pane fade" id="pulse-action-tab"> ');

        // $mform->addElement('html', '</fieldset>'); // E.o of actions triggere tab.

        $actionplugins = new \mod_pulse\plugininfo\pulseaction();
        $plugins = $actionplugins->get_plugins_base();

        $option = [];
        foreach ($plugins as $name => $plugin) {
            // Define the form elements inside the definition function.
            $mform->addElement('html', '<div class="tab-pane fcontainer fade" id="pulse-action-'.$name.'"> ');
            $mform->addElement('html', '<h4>'.get_string('pluginname', 'pulseaction_'.$name).'</h4>');
            // Load the instance elements for this action.
            $plugin->load_instance_form($mform, $this);

            $elements = $plugin->default_override_elements();
            $this->load_default_override_elements($elements);
            // $mform->setExpanded($name);
            $mform->addElement('html', html_writer::end_div()); // E.o of actions triggere tab.
        }


    }

    /**
     * Load the default override elements for instances.
     *
     * @param array $elements Config names list to create override by default.
     *
     * @return void
     */
    protected function load_default_override_elements($elements) {

        if (empty($elements)) {
            return false;
        }

        $mform =& $this->_form;

        foreach ($elements as $element) {
            $overridename = "override[$element]";
            $mform->addElement('hidden', $overridename, 1);
            $mform->setType($overridename, PARAM_BOOL);
        }
    }


    public function get_default_values($key) {
        return $this->_form->_defaultValues[$key] ?? [];
    }

    public function definition_after_data() {
        $plugins = \mod_pulse\plugininfo\pulseaction::instance()->get_plugins_base();
        foreach ($plugins as $name => $plugin) {
            $plugin->definition_after_data($this->_form, $this);
        }
    }

    public function validation($data, $files) {

        /*
        $data.
        print_object($data);
        exit;
        */
    }

    /**
     * Load template actions.
     *
     * @param [type] $mform
     * @return void
     */
    /* protected function load_template_actions(&$mform) {

        $actionplugins = new \mod_pulse\plugininfo\pulseaction();
        $plugins = $actionplugins->get_plugins_base();

        $option = [];
        foreach ($plugins as $name => $plugin) {
            $plugin->load_global_form($mform, $this);
        }
    } */

}
