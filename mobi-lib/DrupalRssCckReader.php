<?php

class DrupalRssCckReader {

  private static $stripIllegalXMLChars = TRUE;
  private $document;

  public function  __construct($content) {
    if(self::$stripIllegalXMLChars) {
      $content = self::stripIllegalXMLChars($content);
    }
    $this->document = DOMDocument::loadXML($content);
  }

  public function getItems() {

    $items = array();
    foreach($this->document->getElementsByTagName('item') as $node) {
      $items[] = array(
	'link' => self::getValue($node, 'link'),
	'title' => self::getValue($node, 'title'),
        'pubDate' => strtotime(self::getValue($node, 'pubDate')),
        'guid' => self::getValue($node, 'guid'),
        'categories' => self::getCategories($node),
        'content' => self::getContent($node),
      );
    }
    return $items;
  }
  

  static function getChildNode($xml, $tag) {
    foreach($xml->childNodes as $childNode) {
      if($childNode->nodeName == $tag) {
	return $childNode;
      }
    }
    return NULL;
  }

  static function getValue($xml, $tag) {
    if(!self::hasValue($xml, $tag)) {
      throw new Exception("$tag is missing");
    }
    return self::getChildNode($xml, $tag)->nodeValue;
  }

  static function hasValue($xml, $tag) {
    return (self::getChildNode($xml, $tag) !== NULL);
  }

  static function getCategories($node) {
    $categories = array();
    foreach($node->childNodes as $childNode) {
      if($childNode->nodeName == 'category') {
        $categories[] = array(
          'domain' => $childNode->getAttribute('domain'),
          'value' => $childNode->nodeValue,
        );
      }
    }
    return $categories;
  }

  static function getContent($node) {
    $descriptionHtml = self::getValue($node, "description");
    $doc = DOMDocument::loadHTML('<html><body>' . $descriptionHtml . '</body></html>');
    $descriptionBody = self::getChildNode($doc->documentElement, 'body');

    $fields = array();
    $body = DOMDocument::loadXML("<body></body>");

    // loop thru each dom node seperate nodes which correspond
    // to extra drupal fields from the main drupal content
    foreach($descriptionBody->childNodes as $childNode) {
      if(self::isFieldNode($childNode)) {
        $fields[] = self::getField($childNode);
      } else {
        $childNode = $body->importNode($childNode, TRUE);
        $body->documentElement->appendChild($childNode);
      }
    }

    $bodyHTML = $body->saveXML();
    // remove the '<body>' at begginning
    $bodyHTML = substr($bodyHTML, strpos($bodyHTML, '<body>') + strlen('<body>'));
    // remove the '</body>' at the end
    $bodyHTML = substr($bodyHTML, 0, strlen($bodyHTML) - strlen('</body>')-1);

    $fields[] = array('type' => 'body', 'name' => 'body', 'value' => trim($bodyHTML));
    return $fields;
  }

  static function isFieldNode($node) {
    if($node->nodeName == 'div' && $node->hasAttribute('class')) {
      $class = $node->getAttribute('class');
      return (strpos($class, "field field-type-", 0) === 0);
    } else if($node->nodeName == 'fieldset') {
      return TRUE;
    }
    return FALSE;
  }

  static function getField($node) {
    if($node->nodeName == 'div') {
      return self::getFieldItem($node);
    } else if ($node->nodeName == 'fieldset') {
      $class = $node->getAttribute('class'); // format = "fieldgroup group-$groupName"
      $classParts = explode(" ", $class);

      $items = array();
      foreach($node->childNodes as $childNode) {
        if($childNode->nodeName == 'div') {
          $items[] = self::getFieldItem($childNode);
        };
      }

      return array(
        'type' => 'group',
        'name' => substr($classParts[1], strlen('group-')),
        'value' => $items,
      );    
    }
  }

  static function getFieldItem($node) {
    $class = $node->getAttribute('class'); // format = "field field-type-$fieldType field-field-$fieldName"

    $classParts = explode(' ', $class);

    $fieldType = substr($classParts[1], strlen('field-type-'));
    $fieldName = substr($classParts[2], strlen('field-field-'));
    
    // find the field-items node
    foreach($node->childNodes as $candidate) {
      if($candidate->nodeName == 'div' && $candidate->getAttribute('class') == 'field-items') {
        $fieldItems = $candidate;
        break;
      }
    }
    $fieldValueNode = self::getChildNode($fieldItems, 'div');

    if($fieldType == 'text') {
      $fieldValue = trim($fieldValueNode->nodeValue);

    } else if($fieldType == 'number-integer') {
      // surprisingly this does not strictly have to be a number
      $text = trim($fieldValueNode->nodeValue);
      if(is_numeric($text)) {
        $fieldValue = intval($text);
      } else {
        $fieldValue = $text == 'no' ? 0 : 1;
      }

    } else if($fieldType == 'date') {
      $fieldValue = trim(self::getValue($fieldValueNode, 'span'));


    } else if($fieldType == 'datestamp') {
      $valueText = "";
      foreach($fieldValueNode->childNodes as $childNode) {
        if($childNode->nodeName == 'span') {
	  $valueText .= $childNode->nodeValue;
        }
      }

      $parts = explode(" - ", $valueText);
      $startStr = $parts[0] . ' ' . $parts[1];
      if(sizeof($parts) == 4) {
        $endStr = $parts[2] . ' ' . $parts[3];
      } else if(sizeof($parts) == 3) {
        $endStr = $parts[0] . ' ' . $parts[2];
      } else {
        $endStr = NULL;
      }

      $fieldValue = array('start' => strtotime($startStr));
      if($endStr != NULL) {
        $fieldValue['end'] = strtotime($endStr);
      }

    } else if($fieldType == 'filefield') {
      
      // detect the type of file
      $imageNode = self::getChildNode($fieldValueNode, 'img');
      if($imageNode != NULL) {
        $fieldType = 'image';
        $fieldValue = array(
	  'src' => $imageNode->getAttribute('src'),
          'width' => $imageNode->getAttribute('width'),
          'height' => $imageNode->getAttribute('height'),
	);
      } 
    }

    return array('type' => $fieldType, 'name' => $fieldName, 'value' => $fieldValue);
  }  
    

  public static function normalize($fields) {
    $normalized = array();
    foreach($fields as $field) {
      if($field['type'] == 'group') {
        $items = array();
	foreach($field['value'] as $fieldItem) {
	  $items[] = $fieldItem['value'];
        }
        $normalized[$field['name']] = $items;
      } else {
        $normalized[$field['name']] = $field['value'];
      }
    }
    return $normalized;
  }

  private static function stripIllegalXMLChars($content) {
    $validXMLChars = '/[^\x{0009}\x{000a}\x{000d}\x{0020}-\x{D7FF}\x{E000}-\x{FFFD}]+/u';
    return preg_replace($validXMLChars, '',$content);
  }
}

?>