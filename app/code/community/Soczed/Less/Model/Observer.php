<?php
/**
 * NOTICE OF LICENSE
 *
 * This source file is subject to the Open Software License (OSL 3.0)
 * that is bundled with this package in the file LICENSE.txt.
 * It is also available through the world-wide-web at this URL:
 * http://opensource.org/licenses/osl-3.0.php
 *
 * @category   Soczed
 * @package    Soczed_Less
 * @copyright  Copyright (c) 2012 Soczed <magento@soczed.com> (Benoît Leulliette <benoit@soczed.com>)
 * @license    http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

require_once(Mage::getBaseDir('lib').DS.'Soczed'.DS.'less'.DS.'Less.php');

class Soczed_Less_Model_Observer
{
    protected function _getHelper()
    {
        return Mage::helper('less');
    }
    
    protected function _getConfigHelper()
    {
        return Mage::helper('less/config');
    }
    
    protected function _checkWritableFile($file)
    {
        $pathinfo = pathinfo($file);
        
        if (empty($pathinfo['dirname']) || !is_writable($pathinfo['dirname'])) {
            return $this->_getHelper()->__('Directory is not writable');
        }
        if (is_file($file) && !is_writable($file)) {
            return $this->_getHelper()->__('File is not writable');
        }
        
        return true;
    }
    
    protected function _getLessVariables($file)
    {
        // Base variables
        // @todo complete this array with any variable that could be useful
        $variables = array();
        
        // Get additional variables
        $response = new Varien_Object(array('less_variables' => array()));
        
        Mage::dispatchEvent(
            'soczed_less_additional_variables',
            array(
                'response'  => $response,
                'file_name' => $file,
            )
        );
        
        if (is_array($additional = $response->getLessVariables())) {
            $variables = array_merge($variables, $additional);
        }
        
        return $variables;
    }
    
    protected function _getLessFunctions($file)
    {
        // Base functions
        // @todo complete this array with any function that could be useful
        $functions = array();
        
        // Get additional functions
        $response = new Varien_Object(array('less_functions' => array()));
        
        Mage::dispatchEvent(
            'soczed_less_additional_functions',
            array(
                'response'  => $response,
                'file_name' => $file,
            )
        );
        
        if (is_array($additional = $response->getLessFunctions())) {
            $functions = array_merge($functions, $additional);
        }
        
        return $functions;
    }
    
    
    public function beforeLayoutRender($observer)
    {
        if (!$this->_getConfigHelper()->isEnabled()) {
            return;
        }
        
        $layout = Mage::getSingleton('core/layout');
        
        if (($head = $layout->getBlock('head'))
            && ($head instanceof Mage_Page_Block_Html_Head)) {
            $baseJsDir     = Mage::getBaseDir() . DS . 'js' . DS;
            $designPackage = Mage::getDesign();
            $newItems      = $head->getData('items');
            $globalVars    = $this->_getConfigHelper()->getGlobalVariables();
            
            // Cache by file path
            /** @var  Soczed_Less_Model_Mysql4_File $filesCollection */
            $filesCollection = Mage::getModel('less/file')
                ->getCollection()
                ->load();
            
            $filesIds = array_flip($filesCollection->toOptionHash());
            
            foreach ($newItems as $key => $item) {

                if (in_array($item['type'], array('js_css', 'skin_css'))) {
                    // CSS file
                    if (substr($item['name'], -5) == '.less') {
                        // LESS file
                        if ($item['type'] == 'js_css') {
                            $lessFile = $baseJsDir . $item['name'];
                        } else {
                            $lessFile = $designPackage->getFilename($item['name'], array('_type' => 'skin'));
                        }
                        $baseFile = ltrim(str_replace(Mage::getBaseDir(), '', $lessFile), DS);
                        $cssFile  = substr($lessFile, 0, -5) . '.css';


                        try {
                            // Init file config
                            if (isset($filesIds[$baseFile])) {
                                $isNewModel      = false;
                                $model           = $filesCollection->getItemById($filesIds[$baseFile]);
                                $forceRebuild    = (bool)$model->getForceRebuild();
                                $customVars      = $model->getCustomVariables();
                                $useGlobalVars   = (bool)$model->getUseGlobalVariables();
                                $forceGlobalVars = (bool)$model->getForceGlobalVariables();
                            } else {
                                $isNewModel      = true;
                                $model           = null;
                                $forceRebuild    = false;
                                $customVars      = array();
                                $useGlobalVars   = true;
                                $forceGlobalVars = false;
                            }
                            
                            // Get all needed variables for current file
                            if (is_array($customVars)) {
                                $oldVars    = $customVars;
                                $customVars = array();
                                
                                foreach ($oldVars as $oldVar) {
                                    $customVars[$oldVar['code']] = $oldVar['value'];
                                }
                            } else {
                                $customVars = array();
                            }
                            if ($useGlobalVars) {
                                $variables  = array_merge(
                                    ($forceGlobalVars  ? $customVars : $globalVars),
                                    ($forceGlobalVars ? $globalVars : $customVars)
                                );
                            } else {
                                $variables = $customVars;
                            }
                            $variables = array_merge($variables, $this->_getLessVariables($item['name']));

                            try {
                                $parser = new Less_Parser();
                                if(filemtime($lessFile)>filemtime($cssFile) OR $forceRebuild) {
                                    $parser->parseFile($lessFile,Mage::getDesign()->getSkinBaseUrl().DS.'less' );
                                    if (!is_string($result = $this->_checkWritableFile($cssFile))) {
                                        if(is_array($variables) AND count($variables)>1) {
                                            $parser->ModifyVars( $variables );
                                        }

                                        file_put_contents($cssFile, $parser->getCss());
                                    } else {
                                        Mage::throwException($result);
                                    }

                                    if ($isNewModel) {
                                        $model = Mage::getModel('less/file')->setPath($baseFile);
                                    }
                                    $model->setData('force_rebuild',$forceRebuild)->save();

                                }

                            } catch (Exception $e) {
                                if ($this->_getConfigHelper()->getShowErrors()) {
                                    if (!is_string($result = $this->_checkWritableFile($cssFile))) {
                                        file_put_contents($cssFile, "\n/* ".$e->getMessage()." */\n", FILE_APPEND);
                                    }
                                }
                                throw $e;
                            }
                            
                        } catch (Exception $e) {
                            Mage::logException($e);
                        }
                        
                        // Force adding the CSS file instead of Less one
                        $newItems[$key]['name'] = substr($item['name'], 0, -5) . '.css';
                    }
                }
            }
            
            // Replace old items with parsed ones
            $head->setData('items', $newItems);
        }
    }
}