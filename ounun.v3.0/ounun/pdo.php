<?php

namespace ounun;


class pdo
{
    /** @var string 倒序大在前 - 排序 */
    const Order_Desc = 'desc';
    /** @var string 倒序大在前 - 排序 */
    const Order_Asc  = 'asc';

    //（INSERT、REPLACE、UPDATE、DELETE）
    /** @var string 低优先级 */
    const Option_Low_Priority   = 'LOW_PRIORITY';
    /** @var string 高优先级 */
    const Option_High_Priority  = 'HIGH_PRIORITY';
    /** @var string 延时 (仅适用于MyISAM, MEMORY和ARCHIVE表) */
    const Option_Delayed        = 'DELAYED';
    /** @var string 快的 */
    const Option_Quick          = 'QUICK';
    /** @var string 出错时忽视 */
    const Option_Ignore         = 'IGNORE';

    /** @var string utf8 */
    const Charset_Utf8          = 'utf8';
    /** @var string utf8mb4 */
    const Charset_Utf8mb4       = 'utf8mb4';
    /** @var string gbk */
    const Charset_Gbk           = 'gbk';
    /** @var string latin1 */
    const Charset_Latin1        = 'latin1';

    /** @var string Mysql */
    const Driver_Mysql          = 'mysql';

    /** @var string 更新操作Update */
    const Update_Update        = 'update';
    /** @var string 更新操作Cut */
    const Update_Cut           = 'cut';
    /** @var string 更新操作Add */
    const Update_Add           = 'add';

    /** @var \PDO  pdo */
    protected $_pdo            = null; // pdo
    /** @var \PDOStatement  stmt */
    protected $_stmt           = null; // stmt

    /** @var string */
    protected $_last_sql       = '';
    /** @var int */
    protected $_query_times    = 0;

    /** @var string 数据库名称 */
    protected $_database       = '';
    /** @var string 用户名 */
    protected $_username       = '';
    /** @var string 用户密码 */
    protected $_password       = '';
    /** @var string 数据库主机名称 */
    protected $_host           = '';
    /** @var string 数据库主机端口 */
    protected $_post           = 3306;
    /** @var string 数据库charset */
    protected $_charset        = 'utf8'; //'utf8mb4','utf8','gbk',latin1;
    /** @var string pdo驱动默认为mysql */
    protected $_driver         = 'mysql';

    /** @var string 当前table */
    protected $_table  = '';
    /** @var string 参数 */
    protected $_option = '';
    /** @var array 字段 */
    protected $_fields = [];
    /** @var array 排序字段 */
    protected $_order  = [];
    /** @var array 分组字段 */
    protected $_group  = [];
    /** @var string limit条件  */
    protected $_limit  = '';
    /** @var string 条件 */
    protected $_where  = '';
    /** @var array 条件参数 */
    protected $_bind_param      = [];
    /** @var array 插入时已存在数据 更新内容 */
    protected $_duplicate       = [];
    /** @var string 插入时已存在数据 更新的扩展 */
    protected $_duplicate_ext   = '';
    /** @var string 关联表 */
    protected $_join            = '';

    /** @var bool 多条 */
    protected $_is_multiple = false;
    /** @var bool 是否替换插入 */
    protected $_is_replace  = false;
    /** @var bool  条件参数 默认true:执行execute  绑定false:bind_param */
//    protected $_is_execute  = true;


    /** @var array DB 相关 */
    private static $_instance = [];

    /**
     * @param string $key
     * @param array  $config
     * @return $this 返回数据库连接对像
     */
    public static function instance(string $key,array $config = []):self
    {
        if(empty(self::$_instance[$key])) {
            if(empty($config)) {
                $config = config::$database[$key];
            }
            self::$_instance[$key] = new self($config);
        }
        return self::$_instance[$key];
    }

    /**
     * 创建MYSQL类
     * pdo constructor.
     * @param array $config
     */
    public function __construct(array $config = [])
    {
        $host            = explode(':',$config['host']);
        $this->_post     = (int)$host[1];
        $this->_host     =      $host[0];
        $this->_database = $config['database'];
        $this->_username = $config['username'];
        $this->_password = $config['password'];

        if($config['charset']){
            $this->_charset = $config['charset'];
        }
        if($config['driver']){
            $this->_driver  = $config['driver'];
        }
    }

    /**
     * @param string $table 表名
     * @return $this
     */
    public function table(string $table = ''):self
    {
        $this->_table = $table;
        return $this;
    }

    /**
     * 发送一条MySQL查询
     * @param string $sql
     * @param bool $check_active
     * @return $this
     */
    public function query(string $sql = '',bool $check_active = true):self
    {
        if($check_active) {
            $this->active();
        }
        if($sql){
            $this->_last_sql  = $sql;
        }
        $this->_stmt = $this->_pdo->query($this->_last_sql);
        $this->_query_times++;
        return $this;
    }

    /**
     * 激活当前连接
     *   主要是解决如果多个库在同一个MYSQL实例时会出现不能自动切换
     * @return $this
     */
    public function active():self
    {
        if(null == $this->_pdo){
            $dsn         = "{$this->_driver}:host={$this->_host};port={$this->_post};dbname={$this->_database};charset={$this->_charset}";
            $options     = [];
            if(self::Driver_Mysql == $this->_driver) {
                $options = [
                    \PDO::ATTR_ERRMODE            => \PDO::ERRMODE_EXCEPTION,
                    //\PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES '{$this->_charset}'"
                ];
            }
            $this->_pdo  = new \PDO($dsn,$this->_username,$this->_password,$options);
        }
        return $this;
    }

    /**
     * @param array $data 数据
     * @return int 替换(插入)一條或多条記錄
     */
    public function insert(array $data):int
    {
        $duplicate = '';
        if(($this->_duplicate || $this->_duplicate_ext) && $this->_is_replace == false) {
            $update    = $this->_fields_update($this->_duplicate,$this->_duplicate);
            $duplicate = 'ON DUPLICATE KEY UPDATE '.$this->_duplicate_ext.' '.implode(' , ',$update);
        }

        $fields  = $this->_values_parse($this->_is_multiple?array_shift($data):$data);
        $cols    = array_keys($fields);

        $this->query( ($this->_is_replace?'REPLACE':'INSERT'). ' '.$this->_option.' INTO '.$this->_table.' (`' . implode('`, `', $cols) . '`) VALUES (:' . implode(', :', $cols) . ') '.$duplicate.';');
        if($this->_is_multiple){
            $this->_execute($fields);
            foreach ($data as &$v){
                $fields = $this->_values_parse($v);
                $this->_execute($fields);
            }
        }else{
            $this->_execute($fields);
        }
        return $this->_pdo->lastInsertId();
    }

    /**
     * 用新值更新原有表行中的各列
     * @param array $data     数据
     * @param array $operate
     * @param string $where
     * @param int $limit
     * @return int
     */
    public function update(array $data,  string $where = '', array $operate = [], int $limit = 1):int
    {
        $fields = $this->_values_parse($this->_is_multiple?array_shift($data):$data);
        $update = $this->_fields_update($fields,$operate);

        $this->where($where)->limit($limit);
        $this->query('UPDATE '.$this->_option.' '.$this->_table.' SET '.implode(', ',$update).' '.$this->_where.' '.$this->_limit.' ;');

        if($this->_is_multiple){
            $this->_execute(array_merge($this->_bind_param,$fields));
            foreach ($data as &$v){
                $fields = $this->_values_parse($v);
                $this->_execute(array_merge($this->_bind_param,$fields));
            }
        }else{
            $this->_execute(array_merge($this->_bind_param,$fields));
        }
        return $this->_stmt->rowCount();
    }

    /**
     * @return int
     */
    public function column_count():int
    {
        $this->query('SELECT '.implode(',',$this->_fields).' FROM '.$this->_table.' '.$this->_join.' '.$this->_where.' '.$this->_get_group().' ;')
             ->_execute($this->_bind_param);
        return $this->_stmt->columnCount();
    }

    /**
     * @return array 得到一条数据数组
     */
    public function column_one()
    {
        $this->query('SELECT '.implode(',',$this->_fields).' FROM '.$this->_table.' '.$this->_join.' '.$this->_where.' '.$this->_get_group().' '.$this->_get_order().' '.$this->_limit.';')
             ->_execute($this->_bind_param);
        return $this->_stmt->fetch(\PDO::FETCH_ASSOC);
    }

    /**
     * @return array 得到多条數椐數組的数组
     */
    public function column_all()
    {
        $this->query('SELECT '.implode(',',$this->_fields).' FROM '.$this->_table.' '.$this->_join.' '.$this->_where.' '.$this->_get_group().' '.$this->_get_order().' '.$this->_limit.';')
             ->_execute($this->_bind_param);
        return $this->_stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    /**
     * 删除
     * @param int $limit 删除limit默认为1
     * @return int
     */
    public function delete(int $limit = 1):int
    {
        $this->limit($limit)
             ->query('DELETE '.$this->_option.' FROM '.$this->_table.' '.$this->_where.' '.$this->_limit.';')
             ->_execute($this->_bind_param);
        return $this->_stmt->rowCount(); //取得前一次 MySQL 操作所影响的记录行数
    }

    /**
     * 设定插入数据为替换
     * @param bool $is_replace
     * @return $this;
     */
    public function replace(bool $is_replace = false):self
    {
        $this->_is_replace = $is_replace;
        return $this;
    }

    /**
     * 多条数据 true:多条数据 false:单条数据
     * @param bool $is_multiple
     * @return $this;
     */
    public function multiple(bool $is_multiple = false):self
    {
        $this->_is_multiple = $is_multiple;
        return $this;
    }

    /**
     * 条件参数 默认true:执行execute  绑定false:bind_param
     * @param bool $is_execute
     * @return $this
     */
//    public function execute(bool $is_execute = false):self
//    {
//        $this->_is_execute = $is_execute;
//        return $this;
//    }

    /**
     * 参数 install update replace
     * @param string $option
     * @return $this
     */
    public function option(string $option = ''):self
    {
        $this->_option = $option;
        return $this;
    }

    /**
     * 插入时已存在数据
     * @param array  $duplicate       更新内容   [字段=>操作]
     * @param string $duplicate_ext   更新的扩展
     * @return $this
     */
    public function duplicate(array $duplicate,string $duplicate_ext = ''):self
    {
        $this->_duplicate     = $duplicate;
        $this->_duplicate_ext = $duplicate_ext;
        return $this;
    }

    /**
     * 指定查询数量
     * @param int $length  查询数量
     * @param int $offset  起始位置
     * @return $this
     */
    public function limit(int $length,int $offset = 0):self
    {
        if(0 == $offset){
            $this->_limit   = "LIMIT {$length}";
        }else{
            $this->_limit   = "LIMIT {$offset},{$length}";
        }
        return $this;
    }

    /**
     * @param string $field
     * @return $this
     */
    public function field($field = '*'):self
    {
        $this->_fields[] = $field;
        return $this;
    }

    /**
     * @param string $inner_join
     * @param string $on
     * @return $this
     */
    public function inner_join(string $inner_join,string $on ):self
    {
        $this->_join = 'INNER JOIN '.$inner_join.' ON '.$on;
        return $this;
    }

    /**
     * @param string $inner_join
     * @param string $on
     * @param bool $is_outer
     * @return $this
     */
    public function full_join(string $inner_join,string $on, bool $is_outer = false ):self
    {
        $outer = $is_outer ? 'OUTER' : '';
        $this->_join = 'FULL '.$outer.' JOIN '.$inner_join.' ON '.$on;
        return $this;
    }

    /**
     * @param string $inner_join
     * @param string $on
     * @param bool $is_outer
     * @return $this
     */
    public function left_join(string $inner_join,string $on, bool $is_outer = false):self
    {
        $outer = $is_outer ? 'OUTER' : '';
        $this->_join = 'LEFT '.$outer.' JOIN '.$inner_join.' ON '.$on;
        return $this;
    }

    /**
     * @param string $inner_join
     * @param string $on
     * @param bool $is_outer
     * @return $this
     */
    public function right_join(string $inner_join,string $on, bool $is_outer = false):self
    {
        $outer = $is_outer ? 'OUTER' : '';
        $this->_join = 'RIGHT '.$outer.' JOIN '.$inner_join.' ON '.$on;
        return $this;
    }

    /**
     * 条件
     * @param string $where  条件
     * @param array  $param  条件参数
     * @return $this
     */
    public function where(string $where = '',array $param = []):self
    {
        if($where) {
            if($this->_where){
                $this->_where = $this->_where.' '.$where;
            }else{
                $this->_where = 'WHERE '.$where;
            }
            if($param && is_array($param)) {
                $this->_bind_param = array_merge($this->_bind_param,$param);
            }
        }
        return $this;
    }

    /**
     * 指定排序
     * @param string $field 排序字段
     * @param string $order 排序
     * @return $this
     */
    public function order(string $field,string $order = self::Order_Desc):self
    {
        $this->_order[] = [ 'field' => $field, 'order' => $order ];
        return $this;
    }

    /**
     * 聚合分组
     * @param string $field
     * @return $this
     */
    public function group(string $field):self
    {
        $this->_group[] = $field;
        return $this;
    }

    /**
     * COUNT查询
     * @param string $field 字段名
     * @param string $alias SUM查询别名
     * @return $this
     */
    public function count(string $field = '*',string $alias = '`count`'):self
    {
        return $this->field("COUNT({$field}) AS {$alias}");
    }

    /**
     * SUM查询
     * @param string $field 字段名
     * @param string $alias SUM查询别名
     * @return $this
     */
    public function sum(string $field,string $alias = '`sum`'):self
    {
        return $this->field("SUM({$field}) AS {$alias}");
    }

    /**
     * MIN查询
     * @param string $field 字段名
     * @param string $alias MIN查询别名
     * @return $this
     */
    public function min(string $field,string $alias = '`min`'):self
    {
        return $this->field("MIN({$field}) AS {$alias}");
    }

    /**
     * MAX查询
     * @param string $field 字段名
     * @param string $alias MAX查询别名
     * @return $this
     */
    public function max(string $field,string $alias = '`max`'):self
    {
        return $this->field("MAX({$field}) AS {$alias}");
    }

    /**
     * AVG查询
     * @param string $field 字段名
     * @param string $alias AVG查询别名
     * @return $this
     */
    public function avg(string $field,string $alias = '`avg`'):self
    {
        return $this->field("AVG({$field}) AS {$alias}");
    }

    /**
     * 返回查询次数
     * @return int
     */
    public function query_times():int
    {
        return $this->_query_times;
    }

    /**
     * 最后一次插入的自增ID
     * @return int
     */
    public function insert_id():int
    {
        return $this->_pdo->lastInsertId();
    }

    /**
     * 最后一次更新影响的行数
     * @return int
     */
    public function affected():int
    {
        return $this->_stmt->columnCount();
    }

    /**
     * 得到最后一次查询的sql
     * @return string
     */
    public function last_sql():string
    {
        return $this->_last_sql;
    }

    /**
     * 返回PDO
     * @return \PDO 返回PDO
     */
    public function pdo():\PDO
    {
        return $this->_pdo;
    }

    /**
     * 是否连接成功
     * @return bool
     */
    public function is_connect():bool
    {
        return $this->_pdo ? true :false;
    }

    /**
     * 捡查指定字段数据是否存在
     * @param string $field 字段
     * @param mixed  $value 值
     * @param int    $param 值数据类型 PDO::PARAM_INT
     * @return bool
     */
    public function is_repeat(string $field,string $value,int $param = \PDO::PARAM_STR):bool
    {
        if($field){
            $k  = $this->_param2types($param);
            $rs = $this->where(" `{$field}` = :field ",[$k.':field'=>$value])->count()->column_one();
            if($rs && $rs['count']){
                return true;
            }
        }
        return false;
    }

    /** order */
    protected function _get_order()
    {
        $rs = '';
        if($this->_order && is_array($this->_order)) {
            $rs2  = [];
            foreach ($this->_order as $v){
                $rs2[] = '`'.$v['field'].'` '.$v['order'];
            }
            $rs  = ' order by '.implode(',',$rs2);
        }
        return $rs;
    }



    /** group */
    protected function _get_group()
    {
        $rs = '';
        if($this->_group && is_array($this->_group)) {
            $rs  = ' group by '.implode(',',$this->_group);
        }
        return $rs;
    }

    /**
     * @param string $types
     * @return int
     */
    protected function _types2param(string $types = '')
    {
        switch ($types) {
            case 'i':
                return \PDO::PARAM_INT;
                break;
            case 'd':
                return \PDO::PARAM_STR;
                break;
            case 'b':
                return \PDO::PARAM_LOB;
                break;
            case 's':
                return \PDO::PARAM_STR;
                break;
            case 'null':
                return \PDO::PARAM_NULL;
                break;
            case 'bool':
                return \PDO::PARAM_BOOL;
                break;
            default:
                return \PDO::PARAM_STR;
        }
    }

    /**
     * @param int $param
     * @return string
     */
    protected function _param2types(int $param = \PDO::PARAM_STR)
    {
        switch ($param) {
            case \PDO::PARAM_INT:
                return 'i';
                break;
            case \PDO::PARAM_STR:
                return 's';
                break;
            case \PDO::PARAM_LOB:
                return 'b';
                break;
            case \PDO::PARAM_NULL:
                return 'null';
                break;
            case \PDO::PARAM_BOOL:
                return 'bool';
                break;
            default:
                return '';
        }
    }

    /**
     * @param array $data
     * @return array
     */
    protected function _values_parse(array &$data)
    {
        $fields   = [];
        if($data && is_array($data)){
            foreach ($data as $col => &$val){
                if('-' == $col[1]){
                    list($type_length,$field) = explode(':',$col);
                    list($type,$length)       = explode('-',$type_length);
                    $fields[$field]           = [
                        'field'  => ':'.$field,
                        'value'  => $val,
                        'type'   => $this->_types2param($type),
                        'length' => $length
                    ];
                }elseif (':' == $col[1]){
                    list($type,$field) = explode(':',$col);
                    $fields[$field]           = [
                        'field'  => ':'.$field,
                        'value'  => $val,
                        'type'   => $this->_types2param($type),
                    ];
                }elseif (':' == $col[0]){
                    list($type,$field) = explode(':',$col);
                    $fields[$field]           = [
                        'field'  => ':'.$field,
                        'value'  => $val,
                        'type'   => $this->_types2param($type),
                    ];
                }else{
                    $fields[$col]           = [
                        'field'  => ':'.$col,
                        'value'  => $val,
                        'type'   => $this->_types2param(''),
                    ];
                }
            }
        }
        return $fields;
    }

    /**
     * @param array $fields_data
     * @param array $operate
     * @return array
     */
    protected function _fields_update(array &$fields_data, array &$operate = [])
    {
        $update = [];
        foreach ($fields_data as $col => &$val) {
            if($operate[$col] == self::Update_Add ) {
                $update[] = "`$col` = `{$col}` + :{$col} ";
            }elseif($operate[$col] == self::Update_Cut ) {
                $update[] = "`$col` = `{$col}` - :{$col} ";
            }else{
                $this->_bind_param[':'.$col] = $val;
                $update[] = "`{$col}` = :{$col} ";
            }
        }
        return $update;
    }

    /**
     * @param array $fields
     */
    protected function _execute(array &$fields)
    {
        foreach ($fields as $k=>&$v){
            if(\PDO::PARAM_STR && isset($v['length'])){
                $this->_stmt->bindParam($v['field'],$v['value'],$v['type'],$v['length']);
            }else{
                $this->_stmt->bindParam($v['field'],$v['value'],$v['type']);
            }
        }
        $this->_stmt->execute();
    }

    /**
     * 当类需要被删除或者销毁这个类的时候自动加载__destruct这个方法
     */
    public function __destruct()
    {
        $this->_stmt = null;
        $this->_pdo  = null;
    }
}