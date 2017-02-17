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

    public function init()
    {
        $this->subscribe('beforeQuestionRender','generateGraphRender');
        $this->subscribe('newQuestionAttributes','addGenerateGraphAttribute');

        /* To add own translation message source */
        $this->subscribe('afterPluginLoad');
    }

    /**
     * generate graph during rendering
     * @see event beforeQuestionRender
     */
    public function generateGraphRender()
    {
        $generateGraphSource=QuestionAttribute::model()->find('qid=:qid and attribute=:attribute',array(':qid'=>$this->getEvent()->get('qid'),':attribute'=>'generateGraphSource'));
        if($generateGraphSource){
            $aGraphData=$this->getGraphData($generateGraphSource->value);
            if(!empty($aGraphData)){
                $oEvent=$this->getEvent();
                $base64image=$this->generateGraphRadar($aGraphData);
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
                    $newHtml = $dom->saveHTMLExact();
                    $oEvent->set('answers',$newHtml);
                }
                $question=$oEvent->get('text');
                $oEvent->set('text',str_replace("[Self.img]","<img src='{$base64image}' />",$question));
                $questionhelp=$oEvent->get('questionhelp');
                $oEvent->set('questionhelp',str_replace("[Self.img]","<img src='{$base64image}' />",$questionhelp));

            }
        }
    }

    /**
     * Generate a radar graph according to data and return a base64 image
     * @var array $aData label and serie
     * @return string
     */
    public function generateGraphRadar($aData,$sTitle=''){
        $aLabels=array_column($aData, 'label');
        $aValues=array_column($aData, 'value');/* Only one serie currently */
        //~ Yii::setPathOfAlias('pChart', dirname(__FILE__)."/vendor/pChart/");
        //~ Yii::import('pChart.pData');
        //~ Yii::import('pChart.pData');
        $fileName="generateGraph".hash("md5",json_encode($aData));
        require_once(Yii::app()->basePath . '/third_party/pchart/pchart/pChart.class');
        require_once(Yii::app()->basePath . '/third_party/pchart/pchart/pData.class');
        require_once(Yii::app()->basePath . '/third_party/pchart/pchart/pCache.class');
        $font=$this->_getChartFontFile();
        // Dataset definition
        $DataSet = new pData;
        $DataSet->AddPoint($aLabels,"Label");
        $DataSet->AddPoint($aValues);
        $DataSet->AddSerie("Serie1");
        $DataSet->SetAbsciseLabelSerie("Label");
        tracevar(count($DataSet->GetData()));
        $Test = new pChart(400,400);
        $Test->setFontProperties($font,8);
        $Test->drawFilledRoundedRectangle(5,5,395,395,5,230,230,230);
        $Test->drawFilledRoundedRectangle(30,30,370,370,5,250,250,250);

        $Test->setGraphArea(40,40,360,360);

        // Draw the radar graph
        $Test->drawRadarAxis($DataSet->GetData(),$DataSet->GetDataDescription(),false,50,0,0,0,255,255,255,200);
        //~ $Test->drawFilledRadar($DataSet->GetData(),$DataSet->GetDataDescription(),50,20);

        $Test->setFontProperties($font,10);
        $Test->drawTitle(0,22,$sTitle,50,50,50,400);
        $path=App()->getRuntimePath().DIRECTORY_SEPARATOR.$fileName.".png";
        $Test->Render($path);
        $data = file_get_contents($path);
        $base64 = 'data:image/png;base64,' . base64_encode($data);
        return $base64;
        //~ return base64_encode($contents);
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
     *
     */
    public function getGraphData($generateGraphSource)
    {
        $generateGraphSource=trim($generateGraphSource);
        $aGraphSources=explode("\n",$generateGraphSource);
        $aGraphData=array();
        foreach($aGraphSources as $graphSource){
            $graphSource=trim($graphSource);
            $aGraphData[]=$this->getDataByCode($graphSource);
        }
        $aGraphData=array_filter($aGraphData);
        return $aGraphData;
    }

    /**
     * Get data value from a EM code
     * @param string : EM code
     * @return array|null
     */
    private function getDataByCode($emCode){
        if(!$emCode){ // invalid
            return;
        }
        $aEmCode=explode("_",$emCode);
        if(count($aEmCode)>2){ // Don't manage multidimensional array
            return;
        }
        /* Find actual answer + question text */
        $questionCode=$aEmCode[0];
        $oQuestion=Question::model()->find('title=:title and language=:language',array(':title'=>$questionCode,':language'=>App()->language));
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
        $value=isset($_SESSION["survey_{$oQuestion->sid}"][$sgq]) ? $_SESSION["survey_{$oQuestion->sid}"][$sgq] : null; // Unsure we need to test : always set to null before render, leave it if API are updated
        return array(
            'label'=>$text,
            'value'=>$value,
        );
    }


    /**
     * just get the font fimle name
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
