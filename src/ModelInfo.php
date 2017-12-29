<?php
// +----------------------------------------------------------------------
// | TwoThink [ WE CAN DO IT JUST THINK IT ]
// +----------------------------------------------------------------------
// | Copyright (c) 2016 http://www.twothink.cn All rights reserved.
// +----------------------------------------------------------------------
// | Author: 艺品网络  82550565@qq.com <www.twothink.cn> 
// +----------------------------------------------------------------------
namespace think;

use think\Request;
use think\modelinfo\Quiet;
use think\modelinfo\System;
use think\modelinfo\Base;
/**
 * 模型解析通用类
 * @author 苹果 <593657688@qq.com>
 */
class ModelInfo{
    /*
     * 模型解析 快速 实例化对象
     * @param $model_info 模型ID或模型定义规则
     * @param $status true 是否查询父级模型(模型ID时有效)
     * @return obj 返回实例化对象
     */
    public function info($model_info=false,$status=true){
        if(empty($model_info))
            return new Base();
        if(is_array($model_info)){
            $class = (new Quiet())->info($model_info);
        }else{
            $class = (new System())->info($model_info,$status);
        }
        return $class;
    }
}