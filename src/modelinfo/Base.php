<?php
// +----------------------------------------------------------------------
// | TwoThink [ WE CAN DO IT JUST THINK IT ]
// +----------------------------------------------------------------------
// | Copyright (c) 2016 http://www.twothink.cn All rights reserved.
// +----------------------------------------------------------------------
// | Author: 艺品网络  82550565@qq.com <www.twothink.cn> 
// +----------------------------------------------------------------------
namespace think\modelinfo;

use think\Db;
use think\db\Connection;
use think\Exception;
use think\Loader;
use think\facade\Request;
use think\facade\App;
use think\exception\ClassNotFoundException;
use think\exception\PDOException;

/*
 * @title 模型解析类公共类
 * @Author: 苹果  593657688@qq.com <www.twothink.cn>
 */
class Base{
    protected $Original;//原始模型数据列表
    protected $info;//解析后的信息

    protected $Queryobj;//实列化查询对象
    public $QueryModel;//绑定模型对象列表
    public $pk = 'id';//主键
    public $scene = false; //应用场景
    protected $options;
    //特殊字符串替换用于列表定义解析  假删除       真删除         编辑       数据恢复      禁用         启用
    public $replace_string = [['[DELETE]','[DESTROY]', '[EDIT]','[RECOVERY]','[DISABLE]','[ENABLE]'], ['del?ids=[id]','destroy?ids=[id]', 'edit?id=[id]','recovery?ids=[id]','status?status=0&ids=[id]','status?status=1&ids=[id]']];

    /*
     * info数据初始化
     */
    public function setInit(){
        $info = $this->info;
        //field_group
        if(isset($info['field_group'])){
            $this->info['field_group'] = parse_config_attr($this->info['field_group']);
        }
        //data
        if(!isset($info['field_default_value']) && isset($info['fields'])){
            $this->FieldDefaultValue();
        }
        //fields:extra
        if(isset($info['fields'])){
            $this->setExtra($this->info['fields']);
        }
        //fields_extend:extra
        if(isset($info['fields_extend'])){
            $this->setFieldsExtend($this->info['fields_extend']);
        }
        return $this;
    }
    /*
     * @title   字段类型extra属性解析
     * @param array  $fields      字段列表
     * @param array  $data        数据(为空  优先info.data info.field_default_value)
     * @param string $extend_name 属性名称
     * @author 艺品网络 593657688@qq.com
     */
    public function setExtra($fields='',$data=''){

        if(empty($fields))
            return false;
        if(empty($data) && isset($this->info['data'])){
            $data = $this->info['data'];
        }
        foreach ($fields as $key=>&$value){
            foreach ($value as $k=>&$v){
                if(isset($v['extra']) && !empty($v['extra'])) {
                    $v['extra'] = parse_field_attr($v['extra'],$data,isset($data[$v['name']])?$data[$v['name']]:'');
                }
            }
        }
        $this->info['fields'] = $fields;
        return $this;
    }
    /*
     * 扩展字段解析
     * @param array $fields_extend 定义规则
     * @param array  $data        数据(为空  优先info.data info.field_default_value)
     */
    public function setFieldsExtend($fields_extend='',$data=''){
        if(empty($fields_extend))
            $fields_extend = isset($this->info['fields_extend'])?$this->info['fields_extend']:'';
        if(empty($fields_extend))
            return false;
        if(empty($data) && isset($this->info['data'])){
            $data = $this->info['data'];
        }
        foreach ($fields_extend as $key=>&$value){
            foreach ($value as $k=>&$v){
                if(isset($v['extra']) && !empty($v['extra'])) {
                    $v['extra'] = parse_field_attr($v['extra'],$data,isset($data[$v['name']])?$data[$v['name']]:'');
                }
            }
        }
        $this->info['fields_extend'] = $fields_extend;
        return $this;
    }
    /*
     * 操作场景(控制器方法)
     * @author 艺品网络 593657688@qq.com
     */
    public function scene($scene = false){
        if($scene)
            $this->scene = $scene;
        return $this;
    }
    /*
     * @title 列表定义解析
     *  @Author: 苹果  593657688@qq.com <www.twothink.cn>
     */
    public function getListField($list_grid=''){
        //解析
        $grids = [];
        if(!empty($list_grid)){
            $grids  = is_array($list_grid)?$list_grid:preg_split('/[;\r\n]+/s', trim($list_grid));
            foreach ($grids as &$value) {
                // 字段:标题:链接
                $val      = explode(':', $value);
                // 支持多个字段显示
                $field   = explode(',', $val[0]);
                $field_name = explode('|', $field[0]);
                $value    = ['name'=>$field_name['0'],'field' => $field, 'title' => $val[1]];
                if(isset($val[2])){
                    // 链接信息
                    $value['href']  =   $val[2];
                }
                if(strpos($val[1],'|')){
                    // 显示格式定义
                    list($value['title'],$value['format'])    =   explode('|',$val[1]);
                }
            }
        }
        $this->info['list_field'] = $grids; //列表规则
        return $this;
    }
    /**
     * @title  获取字段列表配置默认值 函数支持解析的参数默认为requer信息
     * @param $fields array 字段列表
     * @param $data   array 数据
     * @Author: 苹果  593657688@qq.com <www.twothink.cn>
     * @return $obj
     */
    public function FieldDefaultValue($fields=false,$data=''){
        if(!$fields)
            $fields = $this->info['fields'];
        $arr = [];
        if(empty($data)){
            $data = Request::instance()->param();
        }
        $new_arr = [];
        foreach ($fields as $k=>$v){
            foreach ($v as $key=>$value){
                if(isset($value['value'])){
                    if(0 === strpos($value['value'],':') || 0 === strpos($value['value'],'[')) {
                        if(!isset($data[$value['name']])){
                            $data[$value['name']] = '';
                        }
                        $value['value'] = parse_field_attr($value['value'],$data,$data[$value['name']]);
                    }
                    if(is_numeric($k)){ //数字下标字符串
                        $new_arr[$value['name']] = $value['value'];
                    }else{
                        $new_arr[$k][$value['name']] = $value['value'];
                    }
                }
            }
        }
        $this->info['field_default_value'] = $new_arr;
        return $this;
    }
    /*
     * @title   拼装搜索条件
     *
     * @$where_default [] 默认搜索条件 在所有请求查询条件的为空情况下启用设置怎不使用模型配置的条件
     * @$where_solid [] 固定搜索条件 在所有条件下都会加上该条件
     * @$relation 是否关联查询
     * @author 艺品网络 593657688@qq.com
     */
    public function getWhere($where_default=false,$where_solid=false,$relation=false){

        $param = request()->param();
        $where=[];
        //默认搜索条件
        if(isset($param['like_seach']) && empty($param['like_seach']) && empty($param['seach_all']) && !$where_default){
            if( $search_list = $this->info['search_list']){
                foreach ($search_list as $value){
                    //表达式为空或者默认值为空不参与搜索
                    if(empty($value['exp']) || empty($value['value']))
                        continue;
                    if(isset($where[$value['name']])){
                        $where[$value['name']]['0']=$where[$value['name']];
                        $where[$value['name']]['1']=$this->QueryExpression($value['exp'],$value['name'],$value['value']);
                    }else{
                        $where[$value['name']] = $this->QueryExpression($value['exp'],$value['name'],$value['value']);
                    }
                }
            }
        }elseif($where_default){
            $where += $where_default;
        }
        //自由组合搜索
        if(!empty($param['seach_all']) && empty($param['like_seach'])){
            $seach_all = $param['seach_all'];
            foreach ($seach_all['exp'] as $key => $value) {
                //表达式为空不参与搜索
                if(empty($value))
                    continue;
                $search_arr = $this->QueryExpression($value,$seach_all['value'][$key]);
                if(isset($where[$seach_all['name'][$key]])){
                    $where[$seach_all['name'][$key]]['0']=$where[$seach_all['name'][$key]];
                    $where[$seach_all['name'][$key]]['1']=$search_arr;
                }else{
                    $where[$seach_all['name'][$key]]=$search_arr;
                }
            }
        }elseif (!empty($param['like_seach'])){ //搜索列表定义字段
            if($this->info['list_field']){
                $fields = array_unique(array_column($this->info['list_field'],'name'));
                $fields = implode('|',$fields);
                $where[] = [$fields,'like',"%".$param['like_seach']."%"];
            }else{
                $where[] = [$this->pk,'eq',$param['like_seach']];
            }
        }else{
            $where[$this->pk] = [$this->pk,'gt',0];
        }
        //固定搜索
        if($where_solid){
            $where += $where_solid;
        }
        if(isset($this->info['search_fixed'])){
            foreach ($this->info['search_fixed'] as $value){
                $search_arr = $this->QueryExpression($value['exp'],$value['name'],$value['value']);
                if(isset($where[$value['name']])){
                    $where[$value['name']]['0']=$where[$value['name']];
                    $where[$value['name']]['1']=$search_arr;
                }else{
                    $where[$value['name']] = $search_arr;
                }
            }
        }
        //是否关联查询
        if($relation){
            $this->getTablePrefixFields();
            $TablePrefixFields = $this->info['TablePrefixFields'];
            $where_key = array_keys($where);
            foreach ($where_key as $key=>&$value){
                $value = $TablePrefixFields[$value];
            }
            $where = array_combine($where_key,$where);
        }

        $this->info['where'] = $where;
        return $this;

    }
    /*
     * @title 查询表达式
     * @param $exp 表达式规则
     * @param $name 名称
     * @param $value 参数
     * @Author: 苹果  593657688@qq.com <www.twothink.cn>
     */
    public function QueryExpression($exp=false,$name,$value){
        switch (trim($exp)) {//判断查询方式
            case 'neq':
                $search_arr=[$name,'neq',$value];
                break;
            case 'lt':
                $search_arr=[$name,'lt',$value];
                break;
            case 'elt':
                $search_arr=[$name,'elt',$value];
                break;
            case 'gt':
                $search_arr=[$name,'gt',$value];
                break;
            case 'egt':
                $search_arr=[$name,'egt',$value];
                break;
            case 'like':
                $search_arr=[$name,'like',"%".$value."%"];
                break;
            default:
                $search_arr=[$name,'eq',$value];
                break;
        }
        return $search_arr;
    }
    /*
     * 获取单条数据信息(使用模型查询)
     * @param  $where 查询条件
     * @return $this
     * @Author: 苹果  593657688@qq.com <www.twothink.cn>
     */
    public function getFind($where=''){
        if(!$this->QueryModel){
            $this->getQueryModel();
        }
        if(empty($where)){
            $param = request()->param();
            $where[] = [$this->pk,'in',$param['id']];
        }
        $data = [];
        foreach ($this->QueryModel as $key=>$value){
            $arr= [];
            if($arr = $value->where($where)->find()){
                $data += $arr->toArray();
            }
        }
        $this->info['data'] = $data;
        return $this;
    }
    /*
     * 删除数据
     * @param  $where 查询条件
     * @return $this
     * @Author: 苹果  593657688@qq.com <www.twothink.cn>
     */
    public function getDel($where){
        if(!$this->QueryModel){
            $this->getQueryModel();
        }
        foreach ($this->QueryModel as $key=>$value){
            if(!$arr = $value->where($where)->delete()){
                return $arr;
            }
        }
        return true;
    }
    /*
     * 获取模型表字段列表带表前缀
     * @param  array  $model_list 模型列表
     * @return array  表名称.字段  数组
     */
    public function getTablePrefixFields($model_list = ''){
        if(empty($model_list))
            $model_list = $this->Original;
        $fieldsArr = [];
        foreach ($model_list as $key=>$value){
            $arr = Db::name($value['name'])->getFields();
            foreach ($arr as $k=>$v){
                $fieldsArr[$v] = $value['name'].'.'.$v;
            }
        }
        $this->info['TablePrefixFields'] = $fieldsArr;
        return $this;
    }
    /*
     * 列表查询 通过模型查询 不支持继承模型规则
     * @param obj $model 实例化模型对象
     */
    public function getModelList($model = '',$where='',$order=''){
        if(empty($model)){
            $this->getQueryModel();
            $model = $this->QueryModel;
        }
        if(is_array($model))
            $model = $model[0];

        if(empty($where))
            $where = $this->info['where'];

        if(empty($order))
            $order = $this->pk.' desc';

        $param = request()->param();

        $listRows = isset($param['limit'])?$param['limit']:config('list_rows.');
        // 分页查询
        $list = $model->where($where)->order($order)->paginate($listRows);
        if(is_object($list)){
            $list=$list->toArray();
        }

        $this->info['data'] = $list;
        return $this;
    }
    /*
     * @title View视图实例化
     * @param $model_list 模型列表
     * @return obj View对象
     * @Author: 苹果  593657688@qq.com <www.twothink.cn>
     */
    public function getView($model_list = false){
        //模型列表
        if(!$model_list)
            $model_list = $this->Original;

        $Basics_modelname = $model_list[0]['name'];
        $Connection = Connection::instance();
        $Basics_model_fields = $Connection->getTableInfo(config('database.prefix').$Basics_modelname,'fields');
        $query_modelobj = Db::view($Basics_modelname,$Basics_model_fields);
        if(count($model_list) > 1){
            for ($i=1; $i<count($model_list); $i++) {
                $table_name = $model_list[$i]['name'];
                $query_modelobj->view($table_name,true,$table_name.'.id='.$Basics_modelname.'.id');
            }
        }
        return $query_modelobj;
    }
    /*
     * @title View视图分页查询
     * @return array 参数模型和父模型的信息集合
     * @Author: 苹果  593657688@qq.com <www.twothink.cn>
     */
    public function getViewList($where=false){
        $param = request()->param();
        if(!$where){
            $where = $this->info['where'];
        }
        //模型列表
        $model_list = $this->Original;
        $Basics_modelname = $model_list[0]['name'];
        $Connection = Connection::instance();
        $Basics_model_fields = $Connection->getTableInfo(config('database.prefix').$Basics_modelname,'fields');
        $query_modelobj = Db::view($Basics_modelname,$Basics_model_fields);
        if(count($model_list) > 1){
            for ($i=1; $i<count($model_list); $i++) {
                $table_name = $model_list[$i]['name'];
                $query_modelobj->view($table_name,true,$table_name.'.id='.$Basics_modelname.'.id');
            }
            $order = 'level desc,'.$this->pk.' desc';
        }else{
            $order = $this->pk.' desc';
        }

//        $field = $this->info['field'] ? $this->info['field']:false;
//        $field = array_combine($field,$field);
//        if($field['id']){
//            $field['id'] = $Basics_modelname.'.id';
//        }

        $listRows = isset($param['limit'])?$param['limit']:config('list_rows');
        // 分页查询
//        $list = $query_modelobj->where($where)->order($order)->field($field)->paginate($listRows);
        $list = $query_modelobj->where($where)->order($order)->paginate($listRows);

        // 获取分页显示
        $page = $list->render();
        if(is_object($list)){
            $list=$list->toArray();
        }
        $this->info['data'] = $list;
        $this->info['page'] = $page;
        return $this;
    }
    /**
     * 实例化模型列表的模型对象
     * @param string    $layer 业务层名称
     * @param string    $base 默认模型名称
     * @param bool   $appendSuffix 是否添加类名后缀
     * @return $this
     * @Author: 苹果  593657688@qq.com <www.twothink.cn>
     */
    public function getQueryModel( $layer = 'model',$base='Base',$appendSuffix=false,$common = 'common'){
        $model_list = $this->Original;
        foreach ($model_list as $key=>$value){
            $name = $value['name'];
            $model[] = $this->getModelClass($name,$layer,$base,$appendSuffix,$common);
        }
        $this->QueryModel = $model;
        return $this;
    }
    /**
     * 实例化（分层）模型
     * @param string $name         Model名称
     * @param string $layer        业务层名称
     * @param string    $base 默认模型名称
     * @param bool   $appendSuffix 是否添加类名后缀
     * @param string $common       公共模块名
     * @return object
     * @Author: 苹果  593657688@qq.com <www.twothink.cn>
     */
    public function getModelClass($name = '', $layer = 'model',$base = 'Base', $appendSuffix = false, $common = 'common'){
        try{
            $new_name = Loader::parseName($name, 1);
            if(isset($this->info['modelpath'])){
                $new_name = $this->info['modelpath'].DIRECTORY_SEPARATOR.$layer.DIRECTORY_SEPARATOR.$new_name;
            }
            $model = model($new_name, $layer, $appendSuffix, $common);
        }
        catch (ClassNotFoundException $e){
//            throw new Exception($e->getMessage());
            $model = $this->getmodelclass_s($name,$layer,$base);
        }
        catch (PDOException $e){
//            throw new Exception($e->getMessage());
            $model = $this->getmodelclass_s($name,$layer,$base);
        }
        return $model;
    }
    protected function getmodelclass_s($name,$layer,$base){
        $path = isset($this->info['basemodelpath']) ?$this->info['basemodelpath'].DIRECTORY_SEPARATOR.$layer:'app\common\\'.$layer;
        $class = $path.'\\'.$base;
        if (class_exists($class)){
            $setname = [];
            if(!empty($name)){
                $setname = ['twothink_name'=>$name];
            }
            $model = new $class($setname);
        }else{
            throw new Exception($class.'类不存在');
        }
        return $model;
    }
    /*
     * 使用模型新增更新数据(包括继承模型)
     * @param  $param 编辑数据
     * @return $this
     * @Author: 苹果  593657688@qq.com <www.twothink.cn>
     */
    public function getUpdate($param=''){
        if(empty($param)){
            $param = request()->param();
        }
        //自动完成
        $param = $this->checkModelAttr($this->info['fields'],$param);
        //获取模型对象
        if(!$this->QueryModel){
            $this->getQueryModel();
        }
        $QueryModel = $this->QueryModel;
        $saveWhere = [];
        if(!empty($param[$this->pk])){
            $saveWhere = [[$this->pk,'in',$param[$this->pk]]];
        }
        $res_id = '';
        foreach ($QueryModel as $model){
            if(!empty($res_id))
                $param[$this->pk] = $res_id;
            $res_id = $model->setSave($param,$saveWhere);
            if(!$res_id){
                $this->error = $model->getError();
                return false;
            }
        }
        return $res_id;
    }

    /*
     * 自动验证
     * @param  array $fields 字段列表
     * @param  array $data   验证数据
     * @return $this
     * @Author: 苹果  593657688@qq.com <www.twothink.cn>
     */
    public function checkValidate($fields=false,$data=false){
        if(!$fields){
            $fields = $this->info['fields'];
        }
        if(is_array($fields)){
            $fields = $this->MergeFields($fields);
        }
        if(count($fields) < 1){
            $fields = [];
        }
        if(!$data){
            $data = request()->param(); //获取数据
        }

        $validate   =   array();
        $validate_scene_field = [];//验证字段
        foreach($fields as $key=>$attr){
            if(!isset($attr['validate_time']))
                continue;
            switch ($attr['validate_time']) {
                case '1':
                    if (empty($data['id'])) {//新增数据
                        // 自动验证规则
                        if(!empty($attr['validate_rule'])) {
                            if($attr['is_must']){// 必填字段
                                $require = 'require|';
                                $require_msg= $attr['title'].'不能为空|';
                            }
                            $msg = $attr['error_info']?$attr['error_info']:$attr['title'].'验证错误';
                            $validate[]=[$attr['name'], $require.$attr['validate_rule'],$require_msg.$msg];
                            $validate_scene_field[] = $attr['name'];//验证字段
                        }elseif($attr['is_must']){
                            $validate[]=[$attr['name'], 'require', $attr['title'].'不能为空'];
                            $validate_scene_field[] = $attr['name'];//验证字段
                        }

                    }
                    break;
                case '2':
                    if (!empty($data['id'])) {//编辑
                        // 自动验证规则
                        if(!empty($attr['validate_rule'])) {
                            if($attr['is_must']){// 必填字段
                                $require = 'require|';
                                $require_msg= $attr['title'].'不能为空|';
                            }
                            $msg = $attr['error_info']?$attr['error_info']:$attr['title'].'验证错误';
                            $validate[]=[$attr['name'], $require.$attr['validate_rule'],$require_msg.$msg];
                            $validate_scene_field[] = $attr['name'];//验证字段
                        }elseif($attr['is_must']){
                            $validate[]=[$attr['name'], 'require', $attr['title'].'不能为空'];
                            $validate_scene_field[] = $attr['name'];//验证字段
                        }
                    }
                    break;
                default:
                    // 自动验证规则
                    if(!empty($attr['validate_rule'])) {
                        if($attr['is_must']){// 必填字段
                            $require = 'require|';
                            $require_msg= $attr['title'].'不能为空|';
                        }
                        $msg = $attr['error_info']?$attr['error_info']:$attr['title'].'验证错误';
                        $validate[]=[$attr['name'], $require.$attr['validate_rule'],$require_msg.$msg];
                        $validate_scene_field[] = $attr['name'];//验证字段
                    }elseif($attr['is_must']){
                        $validate[]=[$attr['name'], 'require', $attr['title'].'不能为空'];
                        $validate_scene_field[] = $attr['name'];//验证字段
                    }
                    break;
            }
        }
        //验证场景
        $scene = isset($this->scene)?$this->scene:request()->action();
        foreach ($this->Original as $value){
            $vli_obg = $this->getModelClass($value['name'],'validate');
            if(method_exists($vli_obg,'Validationrules')){
                $vli_obg->Validationrules(['rule'=>$validate,'scene'=>$scene,'scene_fields'=>$validate_scene_field]);
            }else{
                if(!empty($validate)){
                    $vli_obg = (new \think\Validate())->make($validate);
                }
                $vli_obg->scene($scene);
            }
            if (!$vli_obg->check($data)) {
                $this->error = $vli_obg->getError();
                return false;
            }
        }
        return true;
    }
    /**
     * 检测属性的自动完成属性 并进行验证
     * 验证场景  insert和update二个个场景，可以分别在新增和编辑
     * @$fields 模型字段属性信息(get_model_attribute($model_id,false))
     * @return boolean  验证通过返回自动完成后的数据 失败返回原始数据
     */
    public function checkModelAttr($fields=false,$data=[]){
        if(!$fields){
            $fields = $this->info['fields'];
        }
        if(is_array($fields)){
            $fields = $this->MergeFields($fields);
        }
        $auto_data = $data; //自动完成更新接收数据
        foreach($fields as $key=>$attr){
            if(!isset($attr['auto_time'])){
                continue;
            }
            switch ($attr['auto_time']){
                case '1':
                    if(empty($data['id']) && !empty($attr['auto_rule'])){//新增
                        $auto_data[$attr['name']] = $attr['auto_rule']($data[$attr['name']],$data);
                    }
                    break;
                case '2':
                    if (!empty($data['id']) && !empty($attr['auto_rule'])) {//编辑
                        $auto_data[$attr['name']] = $attr['auto_rule']($data[$attr['name']],$data);
                    }
                    break;
                default:
                    if (!empty($attr['auto_rule'])){//始终
                        $auto_data[$attr['name']] = $attr['auto_rule']($data[$attr['name']],$data);
                    }elseif('checkbox'==$attr['type']){ // 多选型
                        $auto_data[$attr['name']] = isset($data[$attr['name']])?arr2str($data[$attr['name']]):'';
                    }elseif('datetime' == $attr['type'] || 'date' == $attr['type']){ // 日期型
                        $auto_data[$attr['name']] = isset($data[$attr['name']])?strtotime($data[$attr['name']]):'';
                    }
                    break;
            }
        }
        return $auto_data;
    }
    /**
     * 字段分组列表转一维数组
     * @param array $list 列表数据
     * @author 艺品网络 593657688@qq.com
     */
    private function MergeFields($fields=false){
        $attrList = [];
        if (is_array($fields) && $fields){
            foreach ($fields as $key=>$value){
                $attrList = array_merge_recursive($attrList,$value);
            }
        }
        return $attrList;
    }

    /**
     * 对列表数据进行字段映射处理
     * @param array $list 列表数据
     * @param array $int_to_string 映射关系二维数组
     * @return $this
     * @author 艺品网络 593657688@qq.com
     */
    public function parseIntTostring($list=false,$int_to_string=false){
        if(!$list){
            $list = isset($this->info['data']['data'])?$this->info['data']['data']:[];
        }
        if(!$int_to_string && isset($this->info['int_to_string'])){
            $int_to_string = $this->info['int_to_string'];
        }else{
            return $this;
        }
        $this->info['data']['data'] = int_to_string($list,$int_to_string);
        return $this;
    }
    /**
     * 对列表数据进行显示处理
     * @param array $list 列表数据
     * @param array $attrList fields字段列表
     * @return $this
     * @author 艺品网络 593657688@qq.com
     */
    public function parseList($list=false,$attrList=false){
        if(!$list){
            $list = isset($this->info['data']['data'])?$this->info['data']['data']:'';
        }
        if(!$attrList){
            $attrList = isset($this->info['fields'])?$this->info['fields']:'';
        }

        $attrList = $this->MergeFields($attrList);
        $attrList = Array_mapping($attrList,'name');
        if(is_array($list)){
            foreach ($list as $k=>$data){
                foreach($data as $key=>$val){
                    if(isset($attrList[$key])){
                        $extra      =  isset($attrList[$key]['extra'])?$attrList[$key]['extra']:'';
                        $type       =   $attrList[$key]['type'];
                        if('select'== $type || 'checkbox' == $type || 'radio' == $type || 'bool' == $type) {
                            // 枚举/多选/单选/布尔型
                            $options    =   parse_field_attr($extra);
                            if($options && array_key_exists($val,$options)) {
                                $data[$key]    =   $options[$val];
                            }
                        }elseif('date'==$type && is_int($val)){ // 日期型
                            $data[$key]    =  $val?date('Y-m-d',$val):$val;
                        }elseif('datetime' == $type && is_int($val)){ // 时间型
                            $data[$key]    =   $val?date('Y-m-d H:i',$val):$val;
                        }
                    }
                }
                $list[$k]   =   $data;
            }
        }
        $this->info['data']['data']=$list;
        return $this;
    }
    /**
     * 对列表数据进行列表解析
     * @param array $list 列表数据
     * @param array $list_field 列表定义规则
     * @param array $replace_string 字符串替换规则
     * @return $this
     * @author 艺品网络 593657688@qq.com
     */
    public function parseListIntent($list=false,$list_field=false,$replace_string = ''){
        if(!$list){
            $list = $this->info['data']['data'];
        }
        if(!$list_field){
            isset($this->info['list_field'])?$this->info['list_field']:$this->getListField();
            $list_field = $this->info['list_field'];
        }
        if(empty($replace_string) && isset($this->info['replace_string']) && !empty($this->info['replace_string'])){
            $replace_string = $this->info['replace_string'];
        }elseif(empty($replace_string)){
            $replace_string = $this->replace_string;
        }
        $list_data_new = [];
        if(is_array($list)){
            foreach ($list as $k=>$v){
                foreach ($list_field as $key=>$value){
//                    $list_data_new[$k][$key+1] = intent_list_field($v,$value,$replace_string);
                    $list_data_new[$k][$value['name']] = intent_list_field($v,$value,$replace_string);
                }
            }
        }
        $this->info['data']['data']=$list_data_new;
        return $this;
    }
    /**
     * 指定info获取字段 支持字段排除和指定数据字段
     * @param mixed   $field
     * @param boolean $except    是否排除
     * @return $this
     * @author 艺品网络 593657688@qq.com
     */
    public function field($field='', $except = false)
    {
        if (empty($field)) {
            return $this;
        }

        if (is_string($field)) {
            $field = array_map('trim', explode(',', $field));
            $field = array_flip($field);
        }
        if($except){
            $field  = array_diff_key($this->info, $field);
        }else{
            $field = array_intersect_key($this->info, $field);
        }
        $this->options['field'] = $field;
        return $this;
    }
    /**
     * param数据字段转换
     * @author 苹果 593657688@qq.com
     * @param $array 要转换的数组
     * @return 返回param请求数据数组
     */
    protected function buildParam($array=[])
    {
        $data = $this->request->param();
        if (is_array($array)&&!empty($array)){
            foreach( $array as $item=>$value ){
                $data[$item] = $data[$value];
            }
        }
        return $data;
    }
    /*
     * @title 设置模型配置信息
     * @$arr array 支持数组[name=>value]
     * @Author: 苹果  593657688@qq.com <www.twothink.cn>
     */
    public function setInfo($arr,$value = ''){
        if(is_array($arr)){
            foreach ($arr as $key=>$v){
                $this->info[$key] = $v;
            }
        }else{
            $this->info[$arr] = $value;
        }
        return $this;
    }
    /**
     * 修改器 设置数据对象值
     * @access public
     * @param string(array) $name  属性名
     * @param mixed  $value 属性值
     * @return $this
     */
    public function setAttr($name,$value=''){
        if(is_array($name)){
            foreach ($name as $key=>$value){
                $this->$key = $value;
            }
        }else{
            $this->$name = $value;
        }
        return $this;
    }
    /*
    * @title 获取对象值
    * @$param 要获取的参数 支持多级  a.b.c
    * @return array
    * @Author: 苹果  593657688@qq.com <www.twothink.cn>
    */
    public function getParam($param = false){
        if($param){
            if (is_string($param)) {
                if (!strpos($param, '.')) {
                    if($this->options['field'] && $param == 'info')
                        return $this->options['field'];
                    return $this->$param;
                }
                $name = explode('.', $param);
                $arr = $this->toArray($name[0]);
                for ($i=1;$i< count($name);$i++){
                    $arr = $arr[$name[$i]];
                }
                return $arr;
            }
        }else{
            return $this->toArray();
        }
    }
    //对象转数组
    public function toArray($name='info'){
        return (array)$this->$name;
    }
    /**
     * 返回模型的错误信息
     * @access public
     * @return string|array
     */
    public function getError()
    {
        return $this->error;
    }

    public function __set($name, $value)
    {
        $this->$name = $value;
        return $this;
    }
    //获取对象属性值
    public function getObjAttr($name)
    {
        return $this->$name;
    }
}