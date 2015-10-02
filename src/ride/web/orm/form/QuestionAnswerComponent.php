<?php

namespace ride\web\orm\form;

use ride\application\orm\entry\SurveyAnswerEntry;

use ride\library\form\component\AbstractComponent;
use ride\library\form\FormBuilder;

/**
 * Form to edit ORM data
 */
class QuestionAnswerComponent extends AbstractComponent {

    /**
     * Array with the available likert scales
     * @var array
     */
    protected $likertScales;

    /**
     * Sets the likert scales
     * @param array $likertScales
     * @return null
     */
    public function setLikertScales(array $likertScales) {
        $this->likertScales = $likertScales;
    }

    /**
     * Gets the data type for the data of this form component
     * @return string|null A string for a data class, null for an array
     */
    public function getDataType() {
        return 'ride\\application\\orm\\entry\\SurveyAnswerEntry';
    }

    /**
     * Gets the name of this component, used when this component is the root
     * of the form to be build
     * @return string
     */
    public function getName() {
        return 'form-survey-answer';
    }

    /**
     * Parse the entry to form values for the component rows
     * @param mixed $data
     * @return array $data
     */
    public function parseSetData($entry) {
        if (!$entry) {
            return array();
        }
        
        $this->entry = $entry;
        
        $data['answer'] = $entry->getAnswer();
        $data['score'] = $entry->getScore();
        $data['likert'] = $entry->getLikert();
        
        return $data;
    }

    /**
     * Parse the form values of an entry of this component
     * @param array $data
     * @return mixed Entry
     */
    public function parseGetData(array $data) {
        if (!isset($this->entry)) {
            $this->entry = new SurveyAnswerEntry();
        }
        
        $this->entry->setAnswer($data['answer']);
        $this->entry->setScore($data['score']);
        if ($this->likertScales) {
            $this->entry->setLikert($data['likert']);
        }
        
        return $this->entry;
    }

    /**
     * Prepares the form builder by adding row definitions
     * @param \ride\library\form\FormBuilder $builder
     * @param array $options Extra options from the controller
     * @return null
     */
    public function prepareForm(FormBuilder $builder, array $options) {
        $translator = $options['translator'];
        
        $builder->addRow('answer', 'text', array(
            'label' => $translator->translate('label.answer'),
            'validators' => array(
                'required' => array(),
            ),
        ));
        $builder->addRow('score', 'integer', array(
            'label' => $translator->translate('label.survey.score'),
            'description' => $translator->translate('label.survey.score.description'),
            'validators' => array(
                'numeric' => array('required' => true),
            ),
        ));
        if ($this->likertScales) {
            $builder->addRow('likert', 'object', array(
                'label' => $translator->translate('label.likert'),
                'description' => $translator->translate('label.answer.likert.description'),
                'options' => array('' => '---') + $this->likertScales,
                'value' => 'id',
                'property' => 'name',
                'widget' => 'select',
            ));
        }
    }

}
