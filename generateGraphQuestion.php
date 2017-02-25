<?php
/**
 * Description
 *
 * @author Denis Chenu <denis@sondages.pro>
 * @copyright 2017 Denis Chenu <https://www.sondages.pro>
 * @copyright 2017 Réseau en scène Languedoc-Roussillon <https://www.reseauenscene.fr>
 * @license AGPL v3
 * @version 0.0.1
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 */
class generateGraphQuestion extends PluginBase {
    static protected $description = 'Generate graph image in question (with 64 encoded).';
    static protected $name = 'generateGraphQuestion';

    /**
     * @var array _aDatasGraphSources
     * keep in memory , to allow one page view (TODO : control and test)
     */
    private $_aDatasGraphSources;
    /**
     * @var boolean _pageDone
     * Graph is done for the page
     */
    private $_pageDone;

    public function init()
    {
        $this->subscribe('beforeQuestionRender','beforeQuestionRender');
        $this->subscribe('newQuestionAttributes','addGenerateGraphAttribute');

        /* To add own translation message source */
        $this->subscribe('afterPluginLoad');

        $this->subscribe('beforeSurveyPage');
    }

    public function beforeSurveyPage()
    {
        $surveyId=$this->getEvent()->get('surveyId');
        $sessionSurvey=Yii::app()->session["survey_{$surveyId}"];
        $saveSession=false;
        /* Fill generateGraphQuestion session */
        if(!isset($sessionSurvey['generateGraphQuestion'])){
            $aDatasGraphSources=array(
                'state'=>'done'
            );

            $criteria = new CDbCriteria;
            $criteria->join='LEFT JOIN {{questions}} as question ON question.qid=t.qid';
            $criteria->condition='question.sid = :sid and question.language=:language and attribute=:attribute and value=:value';
            $criteria->params=array(':sid'=>$surveyId,':language'=>Yii::app()->getLanguage(),':attribute'=>'generateGraphSource',':value'=>1);
            $oQuestionsGraphSource = QuestionAttribute::model()->findAll($criteria);
            if($oQuestionsGraphSource){
                $aDatasGraphSources['questions']=array();
                foreach($oQuestionsGraphSource as $oQuestionGraphSource){
                    $aGraphData=$this->getGraphData($oQuestionGraphSource->value,$surveyId); // To validate it's OK
                    if(!empty($aGraphData)){
                        $aDatasGraphSources['questions'][$oQuestionGraphSource->qid]=array(
                            'qid'=>$oQuestionGraphSource->qid,
                            'data'=>trim($oQuestionGraphSource->value),
                            'done'=>false,
                        );
                    }
                }
                $aDatasGraphSources['state']='init';
            }
            $sessionSurvey['generateGraphQuestion']=$aDatasGraphSources;
            $saveSession=true;
        } else {
            //tracevar($sessionSurvey['generateGraphQuestion']);
        }
        $aDatasGraphSources=$sessionSurvey['generateGraphQuestion'];
        /* Take the step for each question id in graphsource, need EM, then wait for EM contructed */
        /* This can not be done if survey are all in one page */
        if($aDatasGraphSources['state']=='init' && isset($sessionSurvey['fieldnamesInfo'])){
            $oSurvey=Survey::model()->findByPk($surveyId);
            foreach($aDatasGraphSources['questions'] as $aDataGraphSource){
                switch($oSurvey->format){
                    case 'S':
                        $step=LimeExpressionManager::GetQuestionSeq($aDataGraphSource['qid']);
                        break;
                    case 'G':
                        $oGroupQuestion=Question::model()->find(array("select"=>'gid','condition'=>'qid=:qid','params'=>array(":qid"=>$aDataGraphSource['qid'])));
                        $step=LimeExpressionManager::GetGroupSeq($oGroupQuestion->gid);
                        break;
                    case 'A':
                    default:
                        $step=0;
                }

                $aDatasGraphSources['questions'][$aDataGraphSource['qid']]['step']=$step;
            }
            $aDatasGraphSources['state']='step';
            $saveSession=true;
        }
        $this->_aDatasGraphSources=$aDatasGraphSources;
        if($saveSession){
            $sessionSurvey['generateGraphQuestion']=$this->_aDatasGraphSources;
            Yii::app()->session["survey_{$surveyId}"]=$sessionSurvey;
        }
    }

    /**
     * @see 'beforeQuestionRender'
     */
    public function beforeQuestionRender()
    {
        if(!$this->_pageDone){
            $surveyId=$this->getEvent()->get('surveyId');
            $sessionSurvey=Yii::app()->session["survey_{$surveyId}"];
            $aDatasGraphSources=$this->_aDatasGraphSources;
            if($aDatasGraphSources['state']=='step'){
                $prevStep=$sessionSurvey['prevstep'];
                $actualStep=$sessionSurvey['step'];
                foreach($aDatasGraphSources['questions'] as $aDatasGraphQuestion){
                    $questionStep=$aDatasGraphQuestion['step']+1;
                    if($prevStep<=$questionStep && $actualStep>=$questionStep){
                        $aGraphData=$this->getGraphData($aDatasGraphQuestion['data'],$surveyId);
                        $oQuestion=Question::model()->find("qid=:qid and language=:language",array(":qid"=>$aDatasGraphQuestion['qid'],":language"=>Yii::app()->getLanguage()));
                        if($oQuestion){
                            /* Get the title */
                            $answerSGQ=$oQuestion->sid."X".$oQuestion->gid."X".$oQuestion->qid;
                            $title=trim(str_replace("[Self.img]","",$oQuestion->question));
                            $title=LimeExpressionManager::ProcessString($title);
                            /* Get the size */
                            $oGenerateGraphSize=QuestionAttribute::model()->find('qid=:qid and attribute=:attribute',array(':qid'=>$this->getEvent()->get('qid'),':attribute'=>'generateGraphSize'));
                            $generateGraphSize=($oGenerateGraphSize)? $oGenerateGraphSize->value : 400;
                            /* Generate graph */
                            $base64image=$this->generateGraphRadar($aGraphData,flattenText($title,false,true),$generateGraphSize);
                            $_SESSION["survey_{$surveyId}"][$answerSGQ]=$base64image;
                            $_SESSION["survey_{$surveyId}"]['startingValues'][$answerSGQ]=$base64image;
                            /* Maybe must save the value in DB */
                        }
                    }
                }
            }
            $stepDone=true;
        }
        $this->generateGraphRender(); // If this question have to be show
    }
    /**
     * generate graph during rendering
     * @param integer $iSurveyId
     * @see event beforeQuestionRender
     */
    public function generateGraphRender()
    {
        $generateGraphSource=QuestionAttribute::model()->find('qid=:qid and attribute=:attribute',array(':qid'=>$this->getEvent()->get('qid'),':attribute'=>'generateGraphSource'));

        if($generateGraphSource){
            $aGraphData=$this->getGraphData($generateGraphSource->value,$this->getEvent()->get('surveyId'));
            if(!empty($aGraphData)){
                $oEvent=$this->getEvent();
                $title=$oEvent->get('text');
                $title=trim(str_replace("[Self.img]","",$title));
                $title=LimeExpressionManager::ProcessString($title);
                /* Get the size */
                $oGenerateGraphSize=QuestionAttribute::model()->find('qid=:qid and attribute=:attribute',array(':qid'=>$this->getEvent()->get('qid'),':attribute'=>'generateGraphSize'));
                $generateGraphSize=($oGenerateGraphSize)? $oGenerateGraphSize->value : 400;

                $base64image=$this->generateGraphRadar($aGraphData,flattenText($title,false,true),$generateGraphSize);
                /* Fill the session value */
                $oQuestion=Question::model()->find("qid=:qid",array(':qid'=>$this->getEvent()->get('qid')));
                $answerSGQ=$oQuestion->sid."X".$oQuestion->gid."X".$oQuestion->qid;
                $surveyId=$oEvent->get('surveyId');
                /* Update the answer */
                $_SESSION["survey_{$surveyId}"][$answerSGQ]=$base64image;
                /* Update the answer input */
                $dom = new \toolsDomDocument\SmartDOMDocument();
                $dom->loadPartialHTML($oEvent->get('answers'));
                $inputDom=$dom->getElementById("answer".$answerSGQ);
                if(!is_null($inputDom)){
                    $inputDom->nodeValue = $base64image;
                    $inputDom->setAttribute('class','hidden');
                    $inputDom->setAttribute('aria-hidden',true);
                    $newHtml = $dom->saveHTMLExact();
                    $oEvent->set('answers',$newHtml);
                }
                $question=$oEvent->get('text');
                $oEvent->set('text',str_replace("[Self.img]","<img src='{$base64image}' />",$question));
                $questionhelp=$oEvent->get('questionhelp');
                $oEvent->set('questionhelp',str_replace("[Self.img]","<img src='{$base64image}' />",$questionhelp));
                $oEvent->set('class',$oEvent->get('class')." graph-question");
            }
        }
    }

    /**
     * Generate a radar graph according to data and return a base64 image
     * @var array $aData label and serie
     * @var string $sTitle of the graph
     * @return string
     */
    public function generateGraphRadar($aData,$sTitle='',$size=400){
        $aLabels=array_column($aData, 'label');
        $aValues=array_column($aData, 'value');/* Only one serie currently */
        //~ Yii::setPathOfAlias('pChart', dirname(__FILE__)."/vendor/pChart/");
        //~ Yii::import('pChart.pData');
        //~ Yii::import('pChart.pData');
        $fileName="generateGraph".hash("md5",json_encode($aData));
        require_once(__DIR__ . '/vendor/pChart2/class/pData.class.php');
        require_once(__DIR__ . '/vendor/pChart2/class/pDraw.class.php');
        require_once(__DIR__ . '/vendor/pChart2/class/pRadar.class.php');
        require_once(__DIR__ . '/vendor/pChart2/class/pImage.class.php');
        $DataSet = new pData();
        $DataSet->addPoints($aValues,"Serie1");
        $DataSet->setSerieDescription("Serie1","Serie 1");

        $DataSet->addPoints($aLabels,"Labels");
        $DataSet->setAbscissa("Labels");

        /* TODO : get color and size via a css file in template */
        $Image = new pImage($size,$size+20,$DataSet);
        $Settings = array("R"=>250, "G"=>250, "B"=>250);
        $Image->drawFilledRectangle(0,0,$size,$size+20,$Settings);
        $Image->drawGradientArea(0,0,$size,20,DIRECTION_VERTICAL,array("StartR"=>0,"StartG"=>0,"StartB"=>0,"EndR"=>50,"EndG"=>50,"EndB"=>50,"Alpha"=>100));
        $Image->drawRectangle(0,0,$size-1,$size+19,array("R"=>0,"G"=>0,"B"=>0));
        $font=$this->_getChartFontFile();
        $Image->setFontProperties(array("FontName"=>$font,"FontSize"=>8));
        $Image->drawText(10,16,$sTitle,array("R"=>255,"G"=>255,"B"=>255));
        $Image->setFontProperties(array("FontName"=>$font,"FontSize"=>10,"R"=>80,"G"=>80,"B"=>80));
        $SplitChart = new pRadar();
        $Image->setGraphArea(10,25,$size-10,$size-15);
        $Options=array("Layout"=>RADAR_LAYOUT_STAR);
        $SplitChart->drawRadar($Image,$DataSet,$Options);
        $path=App()->getRuntimePath().DIRECTORY_SEPARATOR.$fileName.".png";
        $Image->Render($path);
        $data = file_get_contents($path);
        $base64 = 'data:image/png;base64,' . base64_encode($data);
        unlink($path);
        return $base64;
    }

    /**
     * Add the question settings
     * @see event newQuestionAttributes
     */
    public function addGenerateGraphAttribute()
    {
        $generateGraphAttributes = array(
            'generateGraphSource'=>array(
                'types'=>'T', /* long text */
                'category'=>$this->_translate('Graph'),
                'sortorder'=>1,
                'inputtype'=>'textarea',
                'default'=>'',
                'help'=>$this->_translate('One source by line, using complete question code, label are question text, value the actual value. If value is not a number, question are not added.'),
                'caption'=>$this->_translate('Graph source for question'),
            ),
            'generateGraphHide'=>array(
                'types'=>'T', /* long text */
                'category'=>$this->_translate('Graph'),
                'sortorder'=>2,
                'inputtype'=>'switch',
                'options'=>array(
                    0=>gT('No'),
                    1=>gT('Yes')
                ),
                'default'=>'1',
                'help'=>$this->_translate('Hide visually the question wrapper. If you use hide question : image are only generated when survey is submitted.'),
                'caption'=>$this->_translate('Hide the question to respondant.'),
            ),
            'generateGraphSize'=>array(
                'types'=>'T', /* long text */
                'category'=>$this->_translate('Graph'),
                'sortorder'=>2,
                'inputtype'=>'integer',
                'default'=>400,
                'help'=>$this->_translate('Total size of the graph, in number of pixel.'),
                'caption'=>$this->_translate('Size.'),
            ),
        );
        if(method_exists($this->getEvent(),'append')) {
            $this->getEvent()->append('questionAttributes', $generateGraphAttributes);
        } else {
            $questionAttributes=(array)$this->event->get('questionAttributes');
            $questionAttributes=array_merge($questionAttributes,$generateGraphAttributes);
            $this->event->set('questionAttributes',$questionAttributes);
        }
    }
    /**
     * Translate a plugin string
     * @param string $string to translate
     * @return string
     */

    private function _translate($string){
        return Yii::t('',$string,array(),'generateGraphQuestion');
    }

    /**
     * Add this translation just after loaded all plugins
     * @see event afterPluginLoad
     */
    public function afterPluginLoad(){
        // messageSource for this plugin:
        $messageMaintenanceMode=array(
            'class' => 'CGettextMessageSource',
            'cacheID' => 'generateGraphQuestionLang',
            'cachingDuration'=>3600,
            'forceTranslation' => true,
            'useMoFile' => true,
            'basePath' => __DIR__ . DIRECTORY_SEPARATOR.'locale',
            'catalog'=>'messages',// default from Yii
        );
        Yii::app()->setComponent('generateGraphQuestion',$messageMaintenanceMode);
    }


    /**
     * get the graph data for a question
     * @param string $generateGraphSource the data to analyse
     * @param integer $iSurveyId
     * @return array
     */
    public function getGraphData($generateGraphSource,$iSurveyId)
    {
        $generateGraphSource=trim($generateGraphSource);
        $aGraphSources=explode("\n",$generateGraphSource);
        $aGraphData=array();
        foreach($aGraphSources as $graphSource){
            $graphSource=trim($graphSource);
            $aGraphData[]=$this->getDataByCode($graphSource,$iSurveyId);
        }
        $aGraphData=array_filter($aGraphData);
        return $aGraphData;
    }

    /**
     * Get data value from a EM code
     * @param string $emCode : EM code
     * @parma integer $iSurveyId : survey id
     * @return array|null
     */
    private function getDataByCode($emCode,$iSurveyId){
        if(!$emCode){ // invalid
            return;
        }
        $aEmCode=explode("_",$emCode);
        if(count($aEmCode)>2){ // Don't manage multidimensional array
            return;
        }
        /* Find actual answer + question text */
        $questionCode=$aEmCode[0];
        $oQuestion=Question::model()->find('title=:title and language=:language and sid=:sid',array(':title'=>$questionCode,':language'=>App()->language,':sid'=>$iSurveyId));
        if(!$oQuestion){ /* Bad question code */
            return;
        }
        $sgq=$oQuestion->sid."X".$oQuestion->gid."X".$oQuestion->qid;
        $text=$oQuestion->question;
        if(isset($aEmCode[1])){
            $oSubQuestion=Question::model()->find('parent_qid=:qid and title=:title and language=:language',array(':qid'=>$oQuestion->qid,':title'=>$aEmCode[1],':language'=>App()->language));
            if(!$oSubQuestion){ /* Bad sub question code */
                return;
            }
            $sgq.=$oSubQuestion->title;
            $text=$oSubQuestion->question;
        }
        $text=LimeExpressionManager::ProcessString($text);
        $value=isset($_SESSION["survey_{$oQuestion->sid}"][$sgq]) ? $_SESSION["survey_{$oQuestion->sid}"][$sgq] : null;
        return array(
            'label'=>$text,
            'value'=>$value,
        );
    }


    /**
     * just get the font file name
     * @return string complete filename (with dir)
     */
    private function _getChartFontFile()
    {
        $language=App()->getLanguage();
        $rootdir = Yii::app()->getConfig("rootdir");
        $chartfontfile = Yii::app()->getConfig("chartfontfile");
        $alternatechartfontfile = Yii::app()->getConfig("alternatechartfontfile");
        if ($chartfontfile=='auto')
        {
            // Tested with ar,be,el,fa,hu,he,is,lt,mt,sr, and en (english)
            // Not working for hi, si, zh, th, ko, ja : see $config['alternatechartfontfile'] to add some specific language font
            $chartfontfile='DejaVuSans.ttf';
            if(array_key_exists($language,$alternatechartfontfile))
            {
                $neededfontfile = $alternatechartfontfile[$language];
                if(is_file($rootdir."/fonts/".$neededfontfile)){
                    $chartfontfile=$neededfontfile;
                }/* Don't break : just leave DejaVuSans */
            }
        }
        return $rootdir.DIRECTORY_SEPARATOR.'fonts'.DIRECTORY_SEPARATOR.$chartfontfile;
    }
}
