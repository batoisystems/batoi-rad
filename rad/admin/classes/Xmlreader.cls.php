<?php
namespace RadAdmin;
use DOMDocument;
use SimpleXMLElement;
use DateTime;
use libxml_use_internal_errors;  // For capturing XML errors
class Xmlreader{
    private $runData = [];
    private $db;
    private $errorHandler;
    public function __construct(array $runData) {
        $this->runData = $runData;
        $this->db = $runData['db'];
        $this->errorHandler = $runData['errorHandler'];
        // print '<pre>';print_r($this->runData['data']);print '</pre>';die('here');
    }
    public function view() {
        if(!$this->runData['route']['alert_from_request']) {
            $this->runData['route']['alert'] = 'info';
            $this->runData['route']['alert_message'] = 'Please upload a valid XML file.';
        }

        $this->runData['route']['h1'] = 'XML Formatter';
        $this->runData['route']['meta_title'] = 'XML Formatter';
        return $this->runData;
    }

    /**
     * process the XML file
     */
    public function process() {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $xmlData = '';
            if (isset($_FILES['xmlFile']['tmp_name']) && is_uploaded_file($_FILES['xmlFile']['tmp_name'])) {
                $xmlData = file_get_contents($_FILES['xmlFile']['tmp_name']);
                // $xmlData = preg_replace('/[\x00-\x1F\x80-\xFF]/', '', $xmlData);
            } elseif (isset($_POST['xmlText']) && !empty($_POST['xmlText'])) {
                $xmlData = $_POST['xmlText'];
                // echo 'XML exists';exit;
            } elseif (isset($_POST['xmlUrl']) && !empty($_POST['xmlUrl'])) {
                $xmlData = file_get_contents($_POST['xmlUrl']);
            }
            
            // echo $xmlData;
            //     exit();
            // Log the raw XML data for debugging
            // error_log("Raw XML Data: " . $xmlData);
    
            if (!empty($xmlData)) {
                $formattedXml = $this->formatXml($xmlData);
                if ($formattedXml === false) {
                    $this->runData['data']['xml'] = "Invalid XML data. Check server logs for details.";
                } else {
                    $this->runData['data']['xml'] = $formattedXml;
                }
            } else {
                $this->runData['data']['xml'] = "Invalid XML data.";
            }
        }
        echo $this->runData['data']['xml']; // Output the XML
        exit();
    }
    
    /**
     * Format and validate the XML data
     */
    private function formatXml($xmlStr) {
        libxml_use_internal_errors(true);  // Enable user error handling

        $xml = simplexml_load_string($xmlStr);

        if ($xml === false) {
            foreach (libxml_get_errors() as $error) {
                // Log XML errors for debugging
                error_log("XML Parsing Error: " . $error->message);
            }
            libxml_clear_errors();  // Clear errors for next xml load
            return false;
        }

        return $this->parseXml($xml);
    }
    
    private function parseXml($xml, $level = 0, $parentID = null) {
        $output = '';
        $accordionID = 'accordion' . rand(1000, 9999);
    
        if ($level === 0) {
            $output .= "<div class='accordion' id='{$accordionID}'>";
        }
    
        foreach ($xml->children() as $child) {
            $randomId = 'node' . rand(1000, 9999);
            $nodeName = $child->getName();
            
            if ($child->count() > 0) {
                $output .= $this->createAccordionHtml($nodeName, $randomId, $accordionID, $level);
                $output .= $this->parseXml($child, $level + 1, $randomId);
            } else {
                $output .= $this->createLeafNodeHtml($nodeName, $child->__toString(), $level);
            }
        }
    
        if ($level === 0) {
            $output .= "</div>"; // Close the accordion div
        }
    
        return $output;
    }
    
    private function createAccordionHtml($nodeName, $randomId, $accordionID, $level) {
        return <<<HTML
        <div class="accordion-item">
            <h2 class="accordion-header" id="heading{$randomId}">
                <button class="accordion-button" type="button" data-bs-toggle="collapse" data-bs-target="#{$randomId}" aria-expanded="true" aria-controls="{$randomId}">
                    {$nodeName}
                </button>
            </h2>
            <div id="{$randomId}" class="accordion-collapse collapse" aria-labelledby="heading{$randomId}" data-bs-parent="#{$accordionID}">
                <div class="accordion-body">
    HTML;
    }
    
    private function createLeafNodeHtml($nodeName, $nodeValue, $level) {
        return <<<HTML
        <div class="ms-{$level}">
            <i class="bi bi-file-earmark-text"></i> {$nodeName}: {$nodeValue}
        </div>
    HTML;
    }
        
}