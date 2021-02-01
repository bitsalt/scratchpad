<?php

/* 
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

class KyaeFrontController {
    
    private $model;
    private $postData;
    private $getData;
    private $formId;
    private $selectedSubject;
    private $selectedLevel;
    private $selectedLesson;
    
    
    
    public function __construct($atts) {
        $this->model = new kyaeModel();
        $this->postData = $_POST;
        $this->getData = $_GET;
        $this->formId = $atts['form_id'];
        $this->selectedSubject = "";
	    $this->selectedLevel = "";
	    $this->selectedLesson = "";
	    $this->selectedSubject = (isset($this->postData['selectedSubject'])) ? $this->postData['selectedSubject'] : '';
        $this->selectedLevel = (isset($this->postData['selectedLevel'])) ? $this->postData['selectedLevel'] : '';
        $this->selectedLesson = (isset($this->postData['selectedLesson'])) ? $this->postData['selectedLesson'] : '';

        // Quick fix for handling case of GET request for partical lesson
        $this->selectedLesson = (isset($this->getData['selectedLesson'])) ? $this->getData['selectedLesson'] : $this->selectedLesson;
        $this->selectedLevel = (isset($this->getData['selectedLevel'])) ? $this->getData['selectedLevel'] : $this->selectedLevel;
        $this->selectedSubject = (isset($this->getData['selectedSubject'])) ? $this->getData['selectedSubject'] : $this->selectedSubject;

        $this->selectedLesson = str_replace('-', ' ', $this->selectedLesson);
        $this->selectedLevel = str_replace('-', ' ', $this->selectedLevel);
        $this->selectedSubject = str_replace('-',' ', $this->selectedSubject);

        // send to router
        $this->router();
        
    }
    
    
    private function router() {
        echo '
            <form id="selectionsForm" method="post" action="">
                <input type="hidden" id="selectedSubject" name="selectedSubject" value="'.$this->selectedSubject.'" />
                <input type="hidden" id="selectedLevel" name="selectedLevel" value="'.$this->selectedLevel.'" />
                <input type="hidden" id="selectedLesson" name="selectedLesson" value="'.$this->selectedLesson.'" />
            </form>';
        
        // check post/get values to determine what to display
        if(!empty($this->selectedLesson)) {

            $meta = $this->showLesson();
        }
        elseif(!empty($this->selectedLevel)) {
            $meta = $this->showLessonsList();
        }
        elseif(!empty($this->selectedSubject)) {
            // show levels for this subject
            $this->showLevels();
        } 
        else {
            // if none of the above (to be added) conditions, just show subject blocks
            $this->showSubjects();
        }
    }
    
    
    
    private function buildJsForNav($data, $type) {
        // array to hold id text for subject fields. Start with units
        $idArr = array();
        
        // build js to handle navigation
        echo '
            <script type="text/javascript">
                window.onload = function() {';
        
        switch($type) {
            case 'subject':
                $idStr = 'selectedSubject';
                echo '
                    var Units = document.getElementById("Units");
                    Units.onclick = function() {
                        jQuery("#selectedUnit").val("1");
                        jQuery("#selectionsForm").submit();
                    }';
        
                break;
            case 'level':
                $idStr = 'selectedLevel';
                break;
            case 'lesson':
                $idStr = 'selectedLesson';
                break;
        }
        
        $i=0;
        foreach($data as $d) {
            // make an ID-friendly string from the subject data
            $dLink = 'kyae_id_'.$i; 
            // ...and store for use when we build the links
            $idArr[$d] = $dLink;

            echo '
            var '.$dLink.' = document.getElementById("'.$dLink.'");
            '.$dLink.'.onclick = function() {
                jQuery("#'.$idStr.'").val("'.$d.'");
                jQuery("#selectionsForm").submit();
            }';
            
            $i++;
        }
        
        echo '
                }
            </script>';
        
        return $idArr;
        
    }
    
    
    public function showLesson() {
        $data = $this->model->getLessonById($this->selectedLesson);
        echo KyaeElements::buildLessonPage($data);
    }
    
    
    protected function showSubjects() {
        $data = $this->model->getSubjects($this->formId);
        
        echo '
                <div class="fusion-three-fourth fusion-layout-column fusion-spacing-yes" style="margin-top:0px;margin-bottom:20px;">
                    <div class="fusion-column-wrapper">
                        <div class="fusion-flip-boxes flip-boxes row fusion-columns-4">';
                        
            
        $i = 1;
        foreach($data as $row) {
	        $escaped = str_replace(' ', '-', $row);
	        $uri = $_SERVER['REQUEST_URI'].$escaped.'/';
            echo KyaeElements::buildFlipBox($row, $row, "", $uri);
            
            if($i % 3 == 0) {
                echo '                
                        </div>
                        <div class="fusion-clearfix"></div>
                        <div class="fusion-flip-boxes flip-boxes row fusion-columns-4">';
            }
            $i++;
        }
        
        echo '
                        </div>
                        <div class="fusion-clearfix"></div>
                    </div>
                </div>';
        
        
        echo $this->buildUnitsBlock(true);
        
        echo '  
                <div class="fusion-clearfix"></div>';
    

    }
    
    
    
    protected function showLevels() {
        $titleData = $this->model->getLevels();
        
        $levelCount = count($titleData);
        
        echo '
                <div class="fusion-three-fourth fusion-layout-column fusion-spacing-yes" style="margin-top:0px;margin-bottom:20px;">
                    <div class="fusion-column-wrapper">
                        <div class="fusion-flip-boxes flip-boxes row fusion-columns-4">';
                        
            
        $i = 1;
        foreach($titleData as $row) {
            // get Level count
            $levelCount = $this->model->getLevelCountForSubject($this->formId, $this->selectedSubject, $row);
            
            $innerText = 'No lessons available';
            if($levelCount) {
                $innerText = $levelCount;
                $innerText .= ($levelCount > 1) ? ' Lessons' : ' Lesson';
            }
            $esc_subj = str_replace(' ', '-', $this->selectedSubject);
            $escaped = str_replace(' ', '-', $row);
	    $base = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
            $uri = "$base$esc_subj/$escaped/";
            echo KyaeElements::buildFlipBox($row, $innerText, $i, $uri);
            
            if($i % 3 == 0) {
                echo '                
                        </div>
                        <div class="fusion-clearfix"></div>
                        <div class="fusion-flip-boxes flip-boxes row fusion-columns-4">';
            }
            $i++;
        }
        
        echo '
                        </div>
                        <div class="fusion-clearfix"></div>

                    </div>

                </div>';
        
        
        echo '  
                <div class="fusion-clearfix"></div>';
    

    }
    
    
    protected function showLessonsList() {
        $data = $this->model->getLessons($this->formId, $this->selectedSubject, $this->selectedLevel);
        
        // build an array of lesson ids for navigation
        $lessonIdArr = array();
        foreach($data as $d) {
            $lessonIdArr[] = $d['id'];
        }
        
        $i=0;
        foreach($data as $d) {
            // build list of strands
            if($this->formId == 1) {
                $strands = $this->buildMathStrands($d);
            }
            elseif($this->formId == 2) {
                $strands = $this->buildRLAStrands($d);
            }
            else {
                $strands = '';
            }
	    $base = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
	    $uri=$base.'Lesson-'.$d['id'];
            echo '
            <div class="fusion-row">
                <style type="text/css">.reading-box-container-1 .element-bottomshadow:before,.reading-box-container-1 .element-bottomshadow:after{opacity:0.7;}</style>
                <div class="fusion-reading-box-container reading-box-container-1" style="margin-bottom:84px;">
                    <div class="reading-box" style="background-color:#f6f6f6;border-width:1px;border-color:#f6f6f6;border-top-width:3px;border-top-color:#252c6b;border-style:solid;">
                        <div class="reading-box-additional">
                            <h2 class="entry-title" data-fontsize="18" data-lineheight="27">
                                <a href="'.$uri.'">'.$d[KYAETranslator::getFieldNumber($this->formId, 'lesson_title')].'</a>
                            </h2>
                            <p>'.$d[KYAETranslator::getFieldNumber($this->formId, 'lesson_purpose')].'</p>

                            
                            <h4>GED Content Areas</h4>
                            '.$strands.'
                        </div>
                    </div>
                </div>';
            
            $i++;
        }
    }

    
    
    private function buildRLAStrands($data) {
        $subjectId = KYAETranslator::getFieldNumber($this->formId, 'subject');
        
        $strandArr = explode(",", $data[$subjectId]);
        
        $count = count($strandArr);
        
        switch($count) {
            case 1:
                return $strandArr[0];
                break;
            case 2:
                return $strandArr[0].' and '.$strandArr[1];
                break;
            default: // more than 2
                $i=1;
                $nextToLast = $count - 1;
                $retStr = '';
                foreach($strandArr as $s) {
                    $retStr .= $s;
                    if($i == $nextToLast) {
                        $retStr .= ' and ';
                    }
                    elseif($i < $count) {
                        $retStr .= ', ';
                    }
                }
                return $retStr;
                break;
        }
    }

    
    private function buildMathStrands($data) {
        // make array of field IDs
        $rawFields = array('mwotl_level_a','mwotl_level_b','mwotl_level_c','mwotl_level_d','mwotl_level_e');
        
        foreach($rawFields as $r) {
            $field = KYAETranslator::getFieldNumber($this->formId, $r);
            if(!empty($data[$field])) {
                $strandData[] = $data[$field];
            }
        }
        
        
        $strandArr = KYAETranslator::extractLongSubjectName($strandData);
        
        $count = count($strandArr);
        
        switch($count) {
            case 1:
                return $strandArr[0];
                break;
            case 2:
                return $strandArr[0].' and '.$strandArr[1];
                break;
            default: // more than 2
                $i=1;
                $nextToLast = $count - 1;
                $retStr = '';
                foreach($strandArr as $s) {
                    $retStr .= $s;
                    if($i == $nextToLast) {
                        $retStr .= ' and ';
                    }
                    elseif($i < $count) {
                        $retStr .= ', ';
                    }
                }
                return $retStr;
                break;
        }
    }


    /**
     * Build block for display of units
     * @return string
     */
    private function buildUnitsBlock($inRightSidebar=false) {
        $units = $this->model->getUnitTitles($this->formId);
        $unitCount = count($units);
        
        $innerText = 'No units available';
        if($unitCount) {
            $innerText = $unitCount;
            $innerText .= ($unitCount > 1) ? ' Units' : ' Unit';
        }
        
        $unitsBlock = '';
        
        if($inRightSidebar) {
        $unitsBlock .= '
                <div class="fusion-one-fourth fusion-layout-column fusion-column-last fusion-spacing-yes fusion-hide-on-mobile" style="margin-top:0px;margin-bottom:20px;">
                    <div class="fusion-column-wrapper">
                        <div class="fusion-widget-area fusion-widget-area-1 fusion-content-widget-area">
                            <div id="text-2" class="fusion-footer-widget-column widget widget_text">
                                <div class="textwidget">
                                    <div style="width:100%">';
        }
        
        $unitsBlock .= KyaeElements::buildFlipBox('Units', $innerText, 'Units', '', '12', '#dee1d6', '#dee1d6', '#747474', '#000000');
        
        if($inRightSidebar) {
            $unitsBlock .= '
            
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                    
                                    </div>
                                </div>
                                <div style="clear:both;"></div>
                            </div>
                            <div class="fusion-additional-widget-content"></div>
                        </div>
                        <div class="fusion-clearfix"></div>
                    </div>    
                </div>';
        }
        
        return $unitsBlock;
    }
    
    
    
}
