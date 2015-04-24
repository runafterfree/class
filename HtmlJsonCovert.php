<?php
/**
 * html和json互相转化。思路：将html利用dom对象转化为数组，需注意的问题
 * 1.同级元素同tag元素下标重复问题
 * 2.非闭合元素特殊处理
 * 3.非标准的如style等的处理
 * 示例：
$html = file_get_contents('http://testing.moacreative.com/job_interview/php/index.html');
$content =  HtmlJsonCovert::html2json($html);   //html转化为json
echo $content;
$html = HtmlJsonCovert::json2html($content);    //json转化为html
echo $html;
**/

class HtmlJsonCovert
{
    static private $_specialTag = array(
        'filter'=>array('style','br'),       //非html文本过滤
        'close'=>array('img','meta','br'),   //不需要闭合的标签
    );

    //定义转化为数组时，各个不同节点的key值
    static private $_tagName = array(
        'attribute'=>'attributes',
        'text'=>'value',
        'node'=>'child_nodes',
    );
    //检查属性是否存在，若存在，返回对应的值，否则返回false
    private static function getAttribute($element, $attr='id')
    {
        foreach ($element->attributes as $attribute)
        {
            if(!empty($attribute->name) && $attribute->name==$attr)
            {
                return $attribute->value;
            }
        }
        return false;
    }


    //将元素转化为数组
    private static function Element2Arr($element)
    {
        static $is_root=true;
        static $repeat = array( //记录同级别同tag元素重复次数
            'a'=>'0',
            'style'=>0,
            'p'=>0,
            'div'=>0,
            'li'=>0,
            'ul'=>0,
            'br'=>0,
        );
        $tagName = $element->tagName;
        if($is_root)
        {
            $re = array($tagName=>array());
            $obj = &$re[$tagName];
        }
        else
        {
            $re = array();
            $obj = &$re;
        }
        $is_root = false;

        if(!empty($element->attributes))
        {
            foreach ($element->attributes as $attribute)
            {
                if(!empty($attribute->name))
                {
                    if(!isset($obj[HtmlJsonCovert::$_tagName['attribute']]))
                        $obj[HtmlJsonCovert::$_tagName['attribute']] = array();
                    $obj[HtmlJsonCovert::$_tagName['attribute']][$attribute->name] = $attribute->value;
                }
            }
        }
        if(!empty($element->childNodes))
        {
            foreach ($element->childNodes as $subElement)
            {
                if(!empty($subElement->tagName) && in_array($subElement->tagName,HtmlJsonCovert::$_specialTag['filter']))
                {
                    if(!isset($obj[HtmlJsonCovert::$_tagName['node']]))
                        $obj[HtmlJsonCovert::$_tagName['node']] = array();
                    $obj[HtmlJsonCovert::$_tagName['node']][$subElement->tagName.'#'.$repeat[$subElement->tagName]]['value'] = $subElement->nodeValue;
                    $repeat[$subElement->tagName]++;
                }
                elseif ($subElement->nodeType == XML_TEXT_NODE) {
                    $obj[HtmlJsonCovert::$_tagName['text']] = $subElement->wholeText;
                }
                else
                {
                    if(!isset($obj[HtmlJsonCovert::$_tagName['node']]))
                        $obj[HtmlJsonCovert::$_tagName['node']] = array();
                    //如果元素id存在，则展示为tagName#id的形式
                    $index = $subElement->tagName;
                    $id = HtmlJsonCovert::getAttribute($subElement, 'id');
                    if($id)
                        $index .= '#'.$id;
                    elseif(array_key_exists($subElement->tagName, $repeat))
                    {
                        $index .= '#'.$repeat[$subElement->tagName];
                        $repeat[$subElement->tagName]++;
                    }
                    $obj[HtmlJsonCovert::$_tagName['node']][$index] = HtmlJsonCovert::Element2Arr($subElement);
                }
            }
        }
        return $re;
    }

    //将html内容转化为json
    public static function html2json($html)
    {
        $dom = new DOMDocument();
        $dom->loadHTML($html);
        $doc =  json_encode(HtmlJsonCovert::Element2Arr($dom->documentElement),JSON_PRETTY_PRINT);
        $doc = preg_replace('/#\d+/U','', $doc);
        return $doc;
    }

    public static function ArrToString($node)
    {
        $str = '';
        foreach($node as $key=>$element)
        {
            if(strpos($key, '#'))   //过滤掉标签的id部分
                $key = strstr($key,'#', true);
            if(in_array($key, HtmlJsonCovert::$_tagName))
            {
                if($key == HtmlJsonCovert::$_tagName['attribute'])  //处理属性
                {
                    foreach($element as $attr=>$val)
                        $str .= ' '.$attr.'="'.$val.'"';
                    $str .= '>';
                }
                elseif($key == HtmlJsonCovert::$_tagName['text'])  //处理值
                {
                    $str .= $element;
                }
                elseif($key == HtmlJsonCovert::$_tagName['node'])   //循环处理子节点
                {
                    foreach($element as $subkey=>$sub)
                    {
                        $str .= HtmlJsonCovert::ArrToString(array($subkey=>$sub));
                    }
                }
            }
            else
            {
                $str .= '<'.$key;
                if(!isset($element[HtmlJsonCovert::$_tagName['attribute']]))
                    $str .= '>';
                $str .= HtmlJsonCovert::ArrToString($element);
            }
        }
        if(!in_array($key, HtmlJsonCovert::$_tagName) && !in_array($key, HtmlJsonCovert::$_specialTag['close']))
            $str .= '</'.$key.'>';

        return $str;
    }
    //将json转化为html
    public static function json2html($string)
    {
        $arr = json_decode($string, true);

        $html = HtmlJsonCovert::ArrToString($arr);
        return $html;
    }
}
