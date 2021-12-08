<?php

namespace Reflexive\Core;

abstract class ModelDAOxml
{
    const FILE_NAME = '';
    const TAG_NAME = '';

    public static function getFilename() {
        $cc = get_called_class();

        return $cc::FILE_NAME;
    }

    public static function getTagName() {
        $cc = get_called_class();

        return $cc::TAG_NAME;
    }

    protected static function load() {
        return simplexml_load_file(DATA_PATH.self::getFilename(), 'SimpleXMLElement', 4);
    }

    protected static function save($dom) {
        return $dom->asXML(self::getFile());
    }

    public static function docLength() {
        return $self->load()->dom->count();
    }

    public static function getAll() {
        $dom = self::load();

        return $nodes = $dom->{self::getTagName()};
    }

    public static function getById($id) {
        $dom = self::load();
        $xpath = new DOMXPath($dom);
        $nodes = $xpath->query('//'.self::getTagName().'[@id="'.$id.'"]');

        return $nodes->item(0);
    }

    public static function create($object) {
        $dom = self::load();

        if(-1 == $object->getId())
            $object->setId(self::docLength());

        $node = $dom->createElement(self::getTagName());

        $node->setAttribute('id', $object->getId());

        $node = $dom->documentElement->appendChild($node);

        return array($dom, $node);
    }

    public static function update($object) {
        $dom = self::load();
        $xpath = new DOMXPath($dom);
        $nodes = $xpath->query('//'.self::getTagName().'[@id="'.$object->getId().'"]');
        $node = $nodes->item(0);

        if($node) {
            $node->setAttribute('id', $object->getId());

            return array($dom, $node);
        } else
            return false;
    }

    public static function delete($object) {
        $dom = self::load();
        $xpath = new DOMXPath($dom);
        $nodes = $xpath->query('//'.self::getTagName().'[@id="'.$id.'"]');

        return $dom->documentElement->removeChild($nodes->item(0));
    }
}
