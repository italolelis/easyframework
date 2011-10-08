<?php

/**
 *  MysqlDatasource é a camada de abstração para bancos de dados
 *  MySQL. A classe provê métodos para criação e execução de consultas e retorno
 *  dos respectivos dados.
 *
 *  @license   http://www.opensource.org/licenses/mit-license.php The MIT License
 *  @copyright Copyright 2011, Easy Framework
 *
 */
class MysqlDatasource extends Datasource {

    /**
     *  DescriÃ§Ã£o das tabelas do banco de dados.
     */
    protected $schema = array();

    /**
     *  Lista das tabelas contidas no banco de dados.
     */
    protected $sources = array();

    /**
     *  MÃ©todos de comparaÃ§Ã£o utilizados nas SQLs.
     */
    protected $comparison = array("=", "<>", "!=", "<=", "<", ">=", ">", "<=>", "LIKE", "REGEXP");

    /**
     *  MÃ©todos de lÃ³gica utilizados nas SQLs.
     */
    protected $logic = array("or", "or not", "||", "xor", "and", "and not", "&&", "not");

    /**
     *  Conexão utilizada pelo banco de dados.
     */
    protected $connection;

    /**
     *  Resultado das consultas ao banco de dados.
     */
    protected $results;

    /**
     *  Verifica se o banco de dados está conectado.
     */
    protected $connected = false;

    /**
     * Verifica a ultima consulta realizada
     * 
     */
    protected $last_query;

    /**
     *  Retorna a conexão com o banco de dados, ou conecta caso a conexão ainda
     *  não tenha sido estabelecida.
     *
     *  @return resource Conexão com o banco de dados
     */
    public function &getConnection() {
        if (!$this->connected):
            $this->connect();
        endif;
        return $this->connection;
    }

    /**
     *  Conecta ao banco de dados.
     *
     *  @return resource Conexão com o banco de dados
     */
    public function connect() {
        //Realiza a conexão com o mysql
        $this->connection = mysqli_connect($this->config["host"], $this->config["user"], $this->config["password"], $this->config["database"]);
        //Se tudo ocorrer normalmente informa a váriavel que o banco está conectado
        $this->connected = true;
        //Habilita a opção de autocommit
        $this->autocommit();
        //Compatibilidade de Caracteres
        $this->setNames();
        //Retorna a conexão
        return $this->connection;
    }

    /**
     *  Desconecta do banco de dados.
     *
     *  @return boolean Verdadeiro caso a conexão tenha sido desfeita
     */
    public function disconnect() {
        //Se a conexão for fechada corretamente
        if ($this->connection->close()) {
            //informa a variável que o banco está desconectado
            $this->connected = false;
            //Seta o link como null
            $this->connection = null;
        }
        //Retorna o resultado da operação
        return!$this->connected;
    }

    public function autocommit($state = true) {
        return $this->connection->autocommit($state);
    }

    /**
     *  Executa uma consulta SQL.
     *
     *  @param string $sql Consulta SQL
     *  @return mixed Resultado da consulta
     */
    public function query($sql = null) {
        $this->getConnection();
        //Salva a consulta
        $this->last_query = $sql;
        //Realiza a consulta
        $this->results = $this->connection->query($sql);
        //Confirma se a consulta foi bem sucedida
        $this->confirm_query();
        //Retorna o resultado da consulta
        return $this->results;
    }

    private function confirm_query() {
        //Se o resultado da consulta for falso
        if (!$this->results) {
            //Informa o erro
            $output = "Database query error" . mysql_error() . "<br/>
                       Last query: $this->last_query";
            die($output);
        }
    }

    /**
     *  Retorna a quantidade de linhas afetadas pela última consulta.
     *
     *  @return integer Quantidade de linhas afetadas
     */
    public function getAffectedRows() {
        return $this->connection->affected_rows();
    }

    public function fetch_array() {
        return $this->results->fetch_array();
    }

    public function fetch_assoc() {
        return $this->results->fetch_assoc();
    }

    public function fetch_object($className = null) {
        return $this->results->fetch_object($className);
    }

    /**
     * Set all fields to the desired type of encoding
     * @param string $encode 
     */
    private function setNames($encode = 'UTF8') {
        return $this->query("SET NAMES '$encode'");
    }

    /**
     *  Lista as tabelas existentes no banco de dados.
     *
     *  @return array Lista de tabelas no banco de dados
     */
    public function listSources() {
        if (empty($this->sources)):
            $this->query("SHOW TABLES FROM {$this->config['database']}");
            while ($source = $this->fetch_array()):
                $this->sources [] = $source[0];
            endwhile;
        endif;
        return $this->sources;
    }

    /**
     *  Descreve uma tabela do banco de dados.
     *
     *  @param string $table Tabela a ser descrita
     *  @return array DescriÃ§Ã£o da tabela
     */
    public function describe($table) {
        if (!isset($this->schema[$table])):
            if (!$this->query("SHOW COLUMNS FROM {$table}"))
                return false;
            $columns = $this->fetch_assoc();
            $schema = array();
            foreach ($columns as $column):
                $schema[$column["Field"]] = array(
                    //"type" => $this->column($column["Type"]),
                    "null" => $column["Null"] == "YES" ? true : false,
                    "default" => $column["Default"],
                    "key" => $column["Key"],
                    "extra" => $column["Extra"]
                );
            endforeach;
            $this->schema[$table] = $schema;
        endif;
        return $this->schema[$table];
    }

    /**
     *  Insere um registro na tabela do banco de dados.
     *
     *  @param string $table Tabela a receber os dados
     *  @param array $data Dados a serem inseridos
     *  @return boolean Verdadeiro se os dados foram inseridos
     */
    public function create($table = null, $data = array()) {
        $insertFields = $insertValues = array();
        $schema = $this->describe($table);
        foreach ($data as $field => $value):
            $column = isset($schema[$field]) ? $schema[$field]["type"] : null;
            $insertFields [] = $field;
            $insertValues [] = $this->value($value, $column);
        endforeach;
        $query = $this->renderSql("insert", array(
            "table" => $table,
            "fields" => join(",", $insertFields),
            "values" => join(",", $insertValues)
                ));
        return $this->query($query);
    }

    /**
     *  Atualiza registros em uma tabela do banco de dados.
     *
     *  @param string $table Tabela a receber os dados
     *  @param array $params ParÃ¢metros da consulta
     *  @return boolean Verdadeiro se os dados foram atualizados
     */
    public function update($table = null, $params = array()) {
        $updateValues = array();
        $schema = $this->describe($table);
        foreach ($params["data"] as $field => $value):
            $updateValues [] = $field . "=" . $this->value($value);
        endforeach;
        $query = $this->renderSql("update", array(
            "table" => $table,
            "conditions" => ($c = $this->sqlConditions($table, $params["conditions"])) ? "WHERE {$c}" : "",
            "order" => is_null($params["order"]) ? "" : "ORDER BY {$params['order']}",
            "limit" => is_null($params["limit"]) ? "" : "LIMIT {$params['limit']}",
            "values" => join(",", $updateValues)
                ));
        return $this->query($query);
    }

    /**
     *    Cria uma consulta SQL baseada de acordo com alguns parÃ¢metros.
     *
     *    @param string $type Tipo da consulta
     *    @param array $data ParÃ¢metros da consulta
     *    @return string Consulta SQL
     */
    public function renderSql($type, $data = array()) {
        switch ($type):
            case "select":
                return "SELECT {$data['fields']} FROM {$data['table']} {$data['conditions']} {$data['groupBy']} {$data['order']} {$data['limit']}";
            case "delete":
                return "DELETE FROM {$data['table']} {$data['conditions']} {$data['order']} {$data['limit']}";
            case "insert":
                return "INSERT INTO {$data['table']}({$data['fields']}) VALUES({$data['values']})";
            case "update":
                return "UPDATE {$data['table']} SET {$data['values']} {$data['conditions']} {$data['order']} {$data['limit']}";
        endswitch;
    }

    /**
     *  Escapa um valor para uso em consultas SQL.
     *
     *  @param string $value Valor a ser escapado
     *  @param string $column Tipo do valor a ser escapado
     *  @return string Valor escapado
     */
    public function value($value, $column = null) {
        switch ($column):
            case "boolean":
                if ($value === true):
                    return "1";
                elseif ($value === false):
                    return "0";
                else:
                    return!empty($value) ? "1" : "0";
            endif;
            case "integer":
            case "float":
                if ($value === "" or is_null($value)):
                    return "NULL";
                elseif (is_numeric($value)):
                    return $value;
            endif;
            default:
                if (is_null($value)):
                    return "NULL";
                endif;
                return "'" . $value . "'";
        endswitch;
    }

    /**
     *  Gera as condiÃ§Ãµes para uma consulta SQL.
     *
     *  @param string $table Nome da tabela a ser usada
     *  @param array $conditions CondiÃ§Ãµes da consulta
     *  @param string $logical Operador lÃ³gico a ser usado
     *  @return string CondiÃ§Ãµes formatadas para consulta SQL
     */
    public function sqlConditions($table, $conditions, $logical = "AND") {
        if (is_array($conditions)):
            $sql = array();
            foreach ($conditions as $key => $value):
                if (is_numeric($key)):
                    if (is_string($value)):
                        $sql [] = $value;
                    else:
                        $sql [] = "(" . $this->sqlConditions($table, $value) . ")";
                    endif;
                else:
                    if (in_array($key, $this->logic)):
                        $sql [] = "(" . $this->sqlConditions($table, $value, strtoupper($key)) . ")";
                    elseif (is_array($value)):
                        foreach ($value as $k => $v):
                            $value[$k] = $this->value($v, null);
                        endforeach;
                        if (preg_match("/([\w_]+) (BETWEEN)/", $key, $regex)):
                            $condition = $regex[1] . " BETWEEN " . join(" AND ", $value);
                        else:
                            $condition = $key . " IN (" . join(",", $value) . ")";
                        endif;
                        $sql [] = $condition;
                    else:
                        $comparison = "=";
                        if (preg_match("/([\w_]+) (" . join("|", $this->comparison) . ")/", $key, $regex)):
                            list($regex, $key, $comparison) = $regex;
                        endif;
                        $value = $this->value($value);
                        $sql [] = "{$key} {$comparison} {$value}";
                    endif;
                endif;
            endforeach;
            $sql = join(" {$logical} ", $sql);
        else:
            $sql = $conditions;
        endif;
        return $sql;
    }

}

?>