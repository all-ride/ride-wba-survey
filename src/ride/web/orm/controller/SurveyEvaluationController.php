<?php

namespace ride\web\orm\controller;

/**
 * Controller to manage the survey evalutations
 */
class SurveyEvaluationController extends ScaffoldController {

    /**
     * Hook to add extra actions in the overview
     * @param string $locale Code of the locale
     * @return array Array with the URL of the action as key and the label as
     * value
     */
    protected function getIndexActions($locale) {
        return array(
            $this->getUrl('system.orm.scaffold.index', array('model' => 'Survey', 'locale' => $locale)) => $this->getTranslator()->translate('title.surveys'),
            $this->getUrl('system.orm.scaffold.index', array('model' => 'SurveyLikert', 'locale' => $locale)) => $this->getTranslator()->translate('title.likert'),
        );
    }

}
