<?php

namespace romston\TemplatedXmlParser;

use SimpleXMLElement;

class Parser
{
    private static $default = '';
    private static $deleteNamespaces = false;
    
    public static function parse(string $xmlString, array $items, array $options = []): array
    {
        if (isset($options['default'])) {
            self::$default = $options['default'];
        }
        
        if (isset($options['delete_namespaces'])) {
            self::$deleteNamespaces = $options['delete_namespaces'];
        }
        
        if (self::$deleteNamespaces) {
            $xmlString = self::deleteNamespacesFromXml($xmlString);
        }
        
        $xml = new SimpleXMLElement($xmlString);
        
        $result = [];
        foreach ($items as $field => $item) {
            $result[$field] = self::processItem($xml, $item);
        }
        
        return $result;
    }
    
    private static function processItem(SimpleXMLElement $xml, $item)
    {
        if (!is_array($item)) {
            return self::getScalar($xml, $item);
        }
        
        if (isset($item['value'])) {
            return $item['value'];
        }
        
        $type = $item['type'] ?? null;
        
        if ($type === 'array') {
            return self::extractArray($xml, $item['path'], $item['fields']);
        }
        
        if ($type === 'join') {
            return self::extractJoined($xml, $item['paths']);
        }
        
        if (isset($item['path_one_of'])) {
            $value = self::extractOneOf($xml, $item['path_one_of']);
        } else {
            $value = self::getScalar($xml, $item['path']);
        }
        
        if ($type === null || $type === 'string') {
            return $value;
        }
        
        if ($type === 'int') {
            return (int)$value;
        }
        
        if ($type === 'bool' || $type === 'boolean') {
            if (strtolower($value) === 'true') {
                return true;
            }

            if (strtolower($value) === 'false') {
                return false;
            }
            return self::$default;
        }
        
        // For unknown types.
        return $value;
    }
    
    private static function getScalar(SimpleXMLElement $xml, string $path): string
    {
        if (self::$deleteNamespaces) {
            $path = self::deleteNamespacesFromPath($path);
        }
        return $xml->xpath($path) ? (string)$xml->xpath($path)[0] : self::$default;
    }
    
    private static function extractArray(SimpleXMLElement $xml, string $rootPath, array $fields): array
    {
        if (self::$deleteNamespaces) {
            $rootPath = self::deleteNamespacesFromPath($rootPath);
        }
        
        $elements = $xml->xpath($rootPath);
        
        $result = [];
        foreach ($elements as $element) {
            $row = [];
            foreach ($fields as $field => $item) {
                $row[$field] = self::processItem($element, $item);
            }
            $result[] = $row;
        }
        return $result;
    }
    
    private static function extractJoined(SimpleXMLElement $xml, array $paths, string $separator = ' '): string
    {
        $result = [];
        foreach ($paths as $path) {
            $pathResult = self::getScalar($xml, $path);
            if ($pathResult) {
                $result[] = $pathResult;
            }
        }
        return implode($separator, $result);
    }
    
    private static function extractOneOf(SimpleXMLElement $xml, array $paths): string
    {
        $value = self::$default;
        foreach ($paths as $path) {
            $value = self::getScalar($xml, $path);
            if ($value !== self::$default) {
                break;
            }
        }
        return $value;
    }
    
    private static function deleteNamespacesFromXml(string $xmlString): string
    {
        // Delete all namespaces declaration. 
        $xmlString = preg_replace(['/xmlns[^=]*="[^"]*"/i', '/xsi[^=]*="[^"]*"/i'], '', $xmlString);
        
        // Delete all namespaces usage.
        $xmlString = preg_replace('/(<\/|<)[a-zA-Z0-9]+:([a-zA-Z0-9]+[ =>])/', '$1$2', $xmlString);
        
        return $xmlString;
    }
    
    private static function deleteNamespacesFromPath(string $path): string
    {
        $elements = explode('/', $path);
        array_walk($elements, static function (&$element) {
            $element = preg_replace('/.*?:/', '', $element);
        });
        return implode('/', $elements);
    }
}
