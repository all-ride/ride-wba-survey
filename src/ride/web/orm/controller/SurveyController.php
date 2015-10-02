<?php

namespace ride\web\orm\controller;

use ride\library\i18n\I18n;
use ride\library\orm\entry\format\EntryFormatter;
use ride\library\orm\model\Model;
use ride\library\reflection\ReflectionHelper;

// use ride\web\orm\form\ScaffoldComponent;
use ride\web\orm\form\QuestionAnswerComponent;
use ride\web\orm\table\scaffold\decorator\DataDecorator;
use ride\web\orm\table\scaffold\decorator\LocalizeDecorator;
use ride\web\orm\table\scaffold\ScaffoldTable;
use ride\web\WebApplication;

class SurveyController extends ScaffoldController {

    /**
     * Hook to add extra actions in the overview
     * @param string $locale Code of the locale
     * @return array Array with the URL of the action as key and the label as
     * value
     */
    protected function getIndexActions($locale) {
        return array(
            $this->getUrl('system.orm.scaffold.index', array('model' => 'SurveyEvaluation', 'locale' => $locale)) => $this->getTranslator()->translate('title.evaluations'),            
            $this->getUrl('system.orm.scaffold.index', array('model' => 'SurveyLikert', 'locale' => $locale)) => $this->getTranslator()->translate('title.likert'),
        );
    }

    /**
     * Action to set a form with a data object to the view
     * @param \ride\library\i18n\I18n $i18n
     * @param string $locale Locale code of the data
     * @param integer $id Primary key of the data object
     * @return null
     */
    public function detailAction(I18n $i18n, $locale, $id) {
        // resolve locale
        $this->locale = $i18n->getLocale($locale)->getCode();

        // resolve entry
        if (!$this->isReadable($id)) {
            throw new UnauthorizedException();
        }

        $entry = $this->getEntry($id);
        if (!$entry) {
            $this->response->setStatusCode(Response::STATUS_CODE_NOT_FOUND);

            return;
        }

        // format entry for title
        $format = $this->model->getMeta()->getFormat(EntryFormatter::FORMAT_TITLE);
        $entryFormatter = $this->orm->getEntryFormatter();
        $title = $entryFormatter->formatEntry($entry, $format);

        $translator = $this->getTranslator();

        // performance table
        $this->model = $this->orm->getSurveyQuestionModel();
        $locales = $i18n->getLocaleCodeList();
        $imageUrlGenerator = $this->dependencyInjector->get('ride\\library\\image\\ImageUrlGenerator');

        $urlBase = $this->getUrl('survey.detail', array(
            'locale' => $this->locale,
            'id' => $id,
        ));
        $urlQuestionEdit = $this->getUrl('survey.question.edit', array(
            'locale' => $this->locale,
            'survey' => $id,
            'id' => '%id%',
        )) . '?referer=' . urlencode($this->request->getUrl());

        $dataDecorator = new DataDecorator($this->model, null, $urlQuestionEdit, 'id');

        $table = new ScaffoldTable($this->model, $this->getTranslator(), $this->locale, true, false);
        $table->addDecorator($dataDecorator);
        if ($this->model->getMeta()->isLocalized()) {
            $table->addDecorator(new LocalizeDecorator($this->model, $urlQuestionEdit, $this->locale, $locales));
        }
        $table->getModelQuery()->addCondition('{survey} = %1%', $id);
        $table->addAction(
            $translator->translate('button.delete'),
            array($this, 'deleteQuestion'),
            $translator->translate('label.table.confirm.delete')
        );

        $form = $this->processTable($table, $urlBase, 10, $this->orderMethod, $this->orderDirection);
        if ($this->response->willRedirect() || $this->response->getView()) {
            return;
        }

        // url's
        $urlBack = $this->request->getQueryParameter('referer');
        if (!$urlBack) {
            $urlBack = $this->getAction(self::ACTION_INDEX);
        }

        $urlQuestionAdd = $this->getUrl('survey.question.add', array(
            'locale' => $locale,
            'survey' => $id,
        ));

        // referer to append to urls
        $urlReferer = '?referer=' . urlencode($this->request->getUrl());

        // set template and vars as response
        $view = $this->setTemplateView('orm/scaffold/detail.survey', array(
            'title' => $title,
            'entry' => $entry,
            'editUrl' => $this->getAction(self::ACTION_EDIT, array('id' => $id)) . $urlReferer,
            'backUrl' => $urlBack,
            'addQuestionUrl' => $urlQuestionAdd . $urlReferer,
            'form' => $form->getView(),
            'table' => $table,
            'locales' => $locales,
            'locale' => $locale,
            'localizeUrl' => $this->getAction(self::ACTION_DETAIL, array('locale' => '%locale%', 'id' => $id)),
        ));

        $form->processView($view);
    }    
    
    /**
     * Action to add or edit a question
     * @param \ride\library\i18n\I18n $i18n
     * @param string $locale Locale code of the data
     * @param string $survey Id of the survey
     * @param string $id Id of the question
     * @return null
     */
    public function questionFormAction(I18n $i18n, $locale, WebApplication $web, ReflectionHelper $reflectionHelper, $survey, $id = null) {
        // resolve locale
        $this->locale = $i18n->getLocale($locale)->getCode();

        // resolve event
        if (!$this->isReadable($survey)) {
            throw new UnauthorizedException();
        }

        $survey = $this->getEntry($survey);
        if (!$survey) {
            $this->response->setStatusCode(Response::STATUS_CODE_NOT_FOUND);

            return;
        }

        // resolve performance
        $this->model = $this->orm->getSurveyQuestionModel();

        if ($id) {
            $question = $this->getEntry($id);
            if (!$question || $question->getSurvey()->getId() !== $survey->getId()) {
                $this->response->setStatusCode(Response::STATUS_CODE_NOT_FOUND);

                return;
            }
        } else {
            $question = $this->createEntry();
            $question->setSurvey($survey);
        }

        // create form
        $translator = $this->getTranslator();
        $likertModel = $this->orm->getSurveyLikertModel();

        $answerComponent = new QuestionAnswerComponent();
        $answerComponent->setLikertScales($likertModel->find());

        $form = $this->createFormBuilder($question);
        $form->setId('form-survey-question');
        $form->addRow('question', 'text', array(
            'label' => $translator->translate('label.question'),
            'validators' => array(
                'required' => array(),
            ),
        ));
        $form->addRow('description', 'text', array(
            'label' => $translator->translate('label.description'),
        ));
        $form->addRow('isOpen', 'option', array(
            'label' => $translator->translate('label.question.open'),
            'description' => $translator->translate('label.question.open.description'),
        ));
        $form->addRow('isMultiple', 'option', array(
            'label' => $translator->translate('label.question.multiple'),
            'description' => $translator->translate('label.question.multiple.description'),
        ));
        $form->addRow('answers', 'collection', array(
            'label' => $translator->translate('label.answers'),
            'type' => 'component',
            'order' => true,
            'options' => array(
                'component' => $answerComponent,
            ),
        ));
        $form = $form->build();

        // handle form
        if ($form->isSubmitted()) {
            try {
                $form->validate();

                // obtain performance from form
                $question = $form->getData();

                $this->model->save($question);

                $this->addSuccess('success.data.saved', array('data' => $question->getQuestion()));

                $this->response->setRedirect($this->getAction(self::ACTION_DETAIL, array('id' => $survey->getId())));

                return;
            } catch (ValidationException $exception) {
                $this->setValidationException($exception, $form);
            }
        }

        $referer = $this->request->getQueryParameter('referer');

        $this->templateForm = 'orm/scaffold/form';

        $this->setFormView($form, $referer, $i18n->getLocaleCodeList(), $locale, $survey);
    }
    
    /**
     * Action to delete the performance entries from the model
     * @param array $entries Array of entries or entry primary keys
     * @return null
     */
    public function deleteQuestion($entries) {
        if (!$entries || !$this->isDeletable()) {
            return;
        }

        $entryFormatter = $this->orm->getEntryFormatter();
        $format = $this->model->getMeta()->getFormat(EntryFormatter::FORMAT_TITLE);

        foreach ($entries as $entry) {
            if (is_numeric($entry)) {
                $entryId = $entry;
            } else {
                $entryId = $entry->id;
            }

            if (!$this->isDeletable($entryId, false)) {

            } else {
                try {
                    if (is_numeric($entry)) {
                        $entry = $this->model->createProxy($entry);
                    }

                    $entry = $this->model->delete($entry);

                    $this->addSuccess('success.data.deleted', array('data' => $entryFormatter->formatEntry($entry, $format)));
                } catch (ValidationException $exception) {
                    $errors = $exception->getAllErrors();
                    foreach ($errors as $fieldName => $fieldErrors) {
                        foreach ($fieldErrors as $fieldError) {
                            $this->addError($fieldError->getCode(), $fieldError->getParameters());
                        }
                    }
                }
            }
        }

        $referer = $this->getReferer($this->request->getUrl());

        $this->response->setRedirect($referer);
    }    
    
    
}