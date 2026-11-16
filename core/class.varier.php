<?php
/**
 * ���� �������� ������� ����� Varier. ����� �������� �� ��������� � �����
 * ���������� � ����������
 * @author korchasa <korchasa[������]gmail[�����]com>
 * @copyright (C) korchasa
 * @access public
 * @version 0.1
 * @package tools
 */
class Varier{
    /**
     * ��������� �������� ����������, � ������������ � ���������� �������� ����������
     * @param  mixed $var ����������
     * @param string $name ��������� ��������
     * @param array $tmpl ������ ����������
     * @return string
     */
    function _get($var, $name, $tmpl) {
        $type = gettype($var);
        switch ($type) {
            default:
                $out = $tmpl['value'].$var.$tmpl['/value'];
                break;
            case 'boolean':
                $out = $tmpl['value'].(($var)? 'true' : 'false').$tmpl['/value'];
                break;
            case 'string':
                $out = $tmpl['size'].strlen($var).$tmpl['/size'].$tmpl['value'].htmlspecialchars($var).$tmpl['/value'];
                break;
            case 'array':
                $values = Varier::_getSection($var,$tmpl,'+');
                $out = $tmpl['size'].count($var).$tmpl['/size']
                .$values;
                break;
            case 'object':
                $class = get_class($var);
                $properties = Varier::_getSection(get_object_vars($var),$tmpl,'properties');
                $methods = Varier::_getSection(get_class_methods($class),$tmpl,'methods');
                $out  = $tmpl['class_name'].$class.$tmpl['/class_name']
                .$tmpl['properties'].$properties.$tmpl['/properties']
                .$tmpl['methods'].$methods.$tmpl['/methods'];
                break;
        }
        return $tmpl['element'].$tmpl['name'].$name.$tmpl['/name'].$tmpl['type'].$type.$tmpl['/type'].$out.$tmpl['/element'];
    }
   
    /**
     * ��������� �������� ������� ����������
     * ��������� �������� ������� ����������, � ������������ � ���������� �������� ����������, � �������� ��������� � ������� ������, ��� �������� ������.
     *
     * @param array $childs ������ ���������
     * @param array $tmpl ������ ����������
     * @param string $name �������� ������
     * @return string
     */
    function _getSection($childs,$tmpl,$name = '+') {
        if(count($childs)) {
            $child_txt = '';
            foreach($childs as $cname=>$cvalue) {
                $child_txt .= Varier::_get($cvalue,$cname,$tmpl);
            }
            $child_txt = call_user_func(array('Varier',$tmpl['section_decorator']),$name,$child_txt);
            return $child_txt;
        }
    }
    /**
     * ����� ��������� XML-��� ��� ������ (�������, ������ � ��������).
     * @param string $name  ������
     * @param string $text �����
     * @return string
     */
    function _getSectionXml($name,$text) {
        return $text;
    }
   
    /**
     * ��������� html-��� ��� ����������� ���-���.
     * @param string $name ��� ����
     * @param string $text �����
     * @return string
     */
    function _getSectionHtml($name,$text) {
        $id = 'id'.round(rand());
        return "<a name=\"anchor_".$id."\" href=\"#\" onClick=\"javascript: var el = document.getElementById('".$id."');if(el.style.display=='none'){el.style.display='';}else{el.style.display='none';}; return false;\">".$name."</a>
        <blockquote>
            <div style=\"display:none;padding-left:10px;width:auto;\" id=\"".$id."\">".$text."</div>
        </blockquote>";
    } 
   
    function getXml($var, $name = 'INFO') {
        $tmpl['element'] = "<element>";
        $tmpl['/element'] = "</element>\n";
        $tmpl['size'] = "<size>";
        $tmpl['/size'] = "</size>\n";
        $tmpl['type'] = "<type>";
        $tmpl['/type'] = "</type>\n";
        $tmpl['name'] = "<name>";
        $tmpl['/name'] = "</name>\n";
        $tmpl['value'] = "<value>";
        $tmpl['/value'] = "</value>";
        $tmpl['elements'] = "<elements>";
        $tmpl['/elements'] = "</elements>\n";
        $tmpl['class_name'] = '<class_name>';
        $tmpl['/class_name'] = "</class_name>\n";
        $tmpl['properties'] = "<properties>";
        $tmpl['/properties'] = "</properties>\n";
        $tmpl['property'] = '<property>';
        $tmpl['/property'] = '</property>';
        $tmpl['methods'] = '<methods>';
        $tmpl['/methods'] = '</methods>';
        $tmpl['method'] = '<method>';
        $tmpl['/method'] = '</method>';
        $tmpl['section_decorator'] = '_getSectionXml';
        return Varier::_get($var,$name,$tmpl);
    }
   
    /**
     * ����������� HTML-�������� ����������
     *
     * @param mixed $var ����������
     * @param string $name ��������  ��������
     * @return string
     */
    function getHtml($var,$name = 'INFO')  {
        $tmpl['element'] = '<li>';
        $tmpl['/element'] = "</li>\n";
        $tmpl['size'] = '<em>[';
        $tmpl['/size'] = ']</em> ';
        $tmpl['type'] = '<em>';
        $tmpl['/type'] = '</em> ';
        $tmpl['name'] = '<strong>';
        $tmpl['/name'] = '</strong> ';
        $tmpl['value'] = '';
        $tmpl['/value'] = '';
        $tmpl['elements'] = '<ul style="list-style-type:none">';
        $tmpl['/elements'] = "</ul>\n";
        $tmpl['class_name'] = '<em>(';
        $tmpl['/class_name'] = ')</em>';
        $tmpl['properties'] = '<ul style="list-style-type:none">';
        $tmpl['/properties'] = "</ul>\n";
        $tmpl['property'] = '<li>';
        $tmpl['/property'] = "</li>\n";
        $tmpl['methods'] = '<ul style="list-style-type:none">';
        $tmpl['/methods'] = "</ul>\n";
        $tmpl['method'] = '<li>';
        $tmpl['/method'] = "</li>\n";
        $tmpl['section_decorator'] = '_getSectionHtml';
        return Varier::_get($var,$name,$tmpl);
    }
}

/*������ ������������� */
/*
class TestClass {
    var $arr = array('foo'=>'bar',2=>1);
    var $boo = true;
    function fooFunction() {}
    function barFunction(){}
}

$test = new TestClass();
$a = array(1=>$test,2=>'foo','bar'=>true);

echo Varier::getXml($a);
echo Varier::getHtml($a,'example');
*/