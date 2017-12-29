<?php
// +----------------------------------------------------------------------
// | TwoThink [ WE CAN DO IT JUST THINK IT ]
// +----------------------------------------------------------------------
// | Copyright (c) 2016 http://www.twothink.cn All rights reserved.
// +----------------------------------------------------------------------
// | Author: 苹果  593657688@qq.com <www.twothink.cn> 
// +----------------------------------------------------------------------
namespace think\modelinfo\facade;

use think\Facade;
/*
 * @Author: 苹果  <593657688@qq.com>
 */
class ModelInfo extends Facade{
    protected static function getFacadeClass()
    {
        return 'think\ModelInfo';
    }
}