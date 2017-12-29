<?php
// +----------------------------------------------------------------------
// | TwoThink [ WE CAN DO IT JUST THINK IT ]
// +----------------------------------------------------------------------
// | Copyright (c) 2016 http://www.twothink.cn All rights reserved.
// +----------------------------------------------------------------------
// | Author: 艺品网络  82550565@qq.com <www.twothink.cn>
// +----------------------------------------------------------------------

namespace think\modelinfo;

use think\Exception;
/*
 * @title静态模型定义处理类
 * @Author: 艺品网络  82550565@qq.com <www.twothink.cn>
 */
class Quiet extends Base
{
    protected $ZOriginal;//最初模型数据
    // 初始化
    public function info($modelinfo)
    {
        $info = $this->ZOriginal = $modelinfo;
        $scene = $this->scene = $this->scene ?: request()->action();
        //当前操作模型信息
        if (isset($info[$scene]) && isset($info['default'])) {
            $info = array_merge($info['default'], $info[$scene]);
        } elseif (isset($info['default'])) {
            $info = $info['default'];
        }
        $this->Original[0] = $info;//原始模型
        //$pk
        if (isset($info['pk'])) {
            $this->pk = $info['pk'];
        }
        //replace_string
        if (empty($info['replace_string'])) {
            $info['replace_string'] = $this->replace_string;
        }
        $info['name'] = !empty($info['name']) ? $info['name'] : request()->controller();
        if (isset($info['url']) && $info['url'] !== false) {
            $info['url'] = $info['url'] !== true ? url($info['url']) : request()->url();
        }
        $this->info = $info;
        //Button
        if (!empty($info['button'])) {
            $this->getButton($info['button']);
        }
        return $this;
    }
    /*
     * @title 获取button组
     * @param $button 按钮规则
     * @Author: 苹果  593657688@qq.com <www.twothink.cn>
     */
    public function getButton($button = '')
    {
        if (!empty($button)) {
            $param = request()->param();
            foreach ($button as $key => &$value) {
                // 替换数据变量
                $url = preg_replace_callback('/\[([a-z_]+)\]/', function ($match) use ($param) {
                    return isset($param[$match[1]]) ? $param[$match[1]] : '';
                }, $value['url']);
                $value['url'] = url($url,'',false);
            }
            $this->info['button'] = $button;
        }
        return $this;
    }

    /*
     * @title 列表定义解析
     * @param $list_grid 列表定义规则
     * @Author: 苹果  593657688@qq.com <www.twothink.cn>
     */
    public function getListField($list_grid = false)
    {
        if (!$list_grid && isset($this->info['list_grid'])) {
            $list_grid = $this->info['list_grid'];
            //删除原规则
            unset($this->info['list_grid']);
        }
        return parent::getListField($list_grid);
    }

    /*
     * @title 获取高级搜索配置
     * @Author: 苹果  593657688@qq.com <www.twothink.cn>
     */
    public function getSearchList()
    {
        $search_arr = isset($this->info['search_list']) ? $this->info['search_list'] : [];
        //value extra规则解析
        foreach ($search_arr as $key => &$value) {
            if (0 === strpos($value['value'], ':') || 0 === strpos($value['value'], '[')) {
                $value['value'] = parse_field_attr($value['value']);
            }
            if (!empty($value['extra'])) {
                $value['extra'] = parse_field_attr($value['extra']);
            }
        }
        $this->info['search_list'] = $search_arr;
        $this->getSearchFixed();//调用固定搜索
        return $this;
    }

    /*
     * @title 获取固定搜索配置
     * @param $search_fixed 固定搜索配置
     * @Author: 苹果  593657688@qq.com <www.twothink.cn>
     */
    public function getSearchFixed($search_fixed = false)
    {
        if (!$search_fixed) {
            $search_fixed = isset($this->info['search_fixed']) ? $this->info['search_fixed'] : [];
        }
        $param = request()->param();
        //value规则解析
        foreach ($search_fixed as $key => &$value) {
            if (0 === strpos($value['value'], ':') || 0 === strpos($value['value'], '[')) {
                $string = $value['value'];
                $str = substr($string, 1);
                if (0 === strpos($str, '[')) {
                    if (preg_match('/\[([a-z_]+)\]/', $str, $matches)) {
                        if (!isset($param[$matches['1']])) {
                            unset($search_fixed[$key]);
                            continue;
                        }
                    }
                }
                $value['value'] = parse_field_attr($string);
            }
        }

        $this->info['search_fixed'] = $search_fixed;
        return $this;
    }

    /*
     * 获取模型字段排序列表
     * @return $this
     * @Author: 苹果  593657688@qq.com <www.twothink.cn>
     */
    public function getFields($fields = false)
    {
        if(!$fields)
            $fields = isset($this->info['fields']) ? $this->info['fields'] : [];
        $new_arr = [];
        foreach ($fields as $key => $value) {
            $data_name = array_column($value,'name');
            if(count($data_name) == count(array_filter($data_name)))
                $new_arr[$key] = Array_mapping($fields[$key],'name');
            else
                $new_arr[$key] = $value;
        }
        $this->info['fields'] = $new_arr;
        return $this;
    }

}
?>