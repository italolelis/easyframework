<?php

App::uses('DboSource', 'Model');
App::uses('ValueParser', 'Model');

/**
 * MySQL DBO driver object
 *
 * Provides connection and SQL generation for MySQL RDMS
 *
 */
class MysqlDatasource extends DboSource {

    /**
     *  The result set from the query.
     */
    protected $result;

    /**
     * The last executed query
     * @var string 
     */
    protected $last_query;

    /**
     * Default values passed to the query
     * @var array 
     */
    protected $params = array(
        'fields' => '*',
        'joins' => array(),
        'conditions' => array(),
        'groupBy' => null,
        'having' => null,
        'order' => null,
        'offset' => null,
        'limit' => null
    );

    /**
     * Connects to the database using options in the given configuration array.
     *
     * @return boolean True if the database could be connected, else false
     * @throws MissingConnectionException
     */
    public function connect() {
        //Realiza a conexão com o mysql
        $dns = "mysql:host={$this->config["host"]};dbname={$this->config["database"]}";
        $this->connection = new PDO($dns, $this->config["user"], $this->config["password"]);
        //Se tudo ocorrer normalmente informa a váriavel que o banco está conectado
        $this->connected = true;
        //Compatibilidade de Caracteres
        $this->setEncoding();
        //Retorna a conexão
        return $this->connection;
    }

    /**
     *  Retorna a conexão com o banco de dados, ou conecta caso a conexão ainda
     *  não tenha sido estabelecida.
     *
     *  @return resource Conexão com o banco de dados
     */
    public function getConnection() {
        if (!$this->connected)
            $this->connect();

        return $this->connection;
    }

    /**
     *  Disconect from the dataSource
     *
     *  @return boolean Verdadeiro caso a conexão tenha sido desfeita
     */
    public function disconnect() {
        //Close the connection
        $this->connection = null;
        $this->connected = false;
        unset($this->connection);

        return !$this->connected;
    }

    public function getLastId() {
        return $this->connection->insert_id;
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
        $this->logQuery($sql);
        //Realiza a consulta
        $this->result = $this->connection->query($sql);
        //Confirma se a consulta foi bem sucedida
        $this->confirm_query($this->result);
        //Retorna o resultado da consulta
        return $this->result;
    }

    public function logQuery($sql) {
        $this->last_query = $sql;
    }

    private function confirm_query($result) {
        if (Config::read("debug")) {
            if (!$result) {
                //Display the error
                $output = "Database query error: " . mysql_error() . "<br/>
                           Last query: {$this->last_query}";
                EasyLog::write('error', $output);
                trigger_error($output, E_USER_ERROR);
            }
        }
    }

    /**
     * Sets the database encoding
     *
     * @param string $enc Database encoding
     * @return boolean
     */
    private function setEncoding($encode = 'UTF8') {
        return $this->query("SET NAMES $encode");
    }

    /**
     * Realiza uma consulta SQL
     * 
     * @param string $sql
     * @return array 
     */
    public function fetchAll($result = null) {
        //Cria um array que receberá os objetos
        $object_array = array();
        //Percorre o resultado da consulta
        while ($row = $result->fetch(PDO::FETCH_OBJ)) {
            //Instância um objeto e coloca no array
            $object_array[] = $row;
        }
        //Retorna o array
        return $object_array;
    }

    /**
     *  Retorna a quantidade de linhas afetadas pela última consulta.
     *
     *  @return integer Quantidade de linhas afetadas
     */
    public function getAffectedRows() {
        return $this->connection->affected_rows;
    }

    /**
     * Used to create new records. The "C" CRUD.
     *
     * @param Model $model The Model to be created.
     * @param array $fields An Array of fields to be saved.
     * @param array $values An Array of values to save.
     * @return boolean success
     */
    public function create($params = array()) {
        foreach ($params["data"] as $field => $value) {
            $insertValues ['fields'][] = $field;
            $insertValues ['values'][] = "'" . $value . "'";
        }
        $insertValues['table'] = $params['table'];
        $query = $this->renderInsert($insertValues);
        return $this->query($query);
    }

    /**
     * Used to read records from the Datasource. The "R" in CRUD
     *
     * @param Model $model The model being read.
     * @param array $queryData An array of query data used to find the data you want
     * @return mixed
     */
    public function read($params) {
        $params += $this->params;

        $query = new ValueParser($params['conditions']);
        $params['conditions'] = $query->conditions();

        $query = $this->renderSelect($params);
        $result = $this->query($query);

        $fetchedResult = $this->fetchAll($result);
        $result->closeCursor();

        return $fetchedResult;
    }

    /**
     * Update a record(s) in the datasource.
     *
     * @param Model $model Instance of the model class being updated
     * @param array $fields Array of fields to be updated
     * @param array $values Array of values to be update $fields to.
     * @return boolean Success
     */
    public function update($params = array()) {
        $params += $this->params;

        $query = new ValueParser($params['conditions']);
        $params['conditions'] = $query->conditions();

        $query = $this->renderUpdate($params);
        return $this->query($query);
    }

    /**
     * Delete a record(s) in the datasource.
     *
     * @param Model $model The model class having record(s) deleted
     * @param mixed $conditions The conditions to use for deleting.
     * @return void
     */
    public function delete($params = array()) {
        $params += $this->params;

        $query = new ValueParser($params['conditions']);
        $params['conditions'] = $query->conditions();

        $query = $this->renderDelete($params);
        echo $query;
        if (!empty($params["conditions"]))
            return $this->query($query);
        else
            return false;
    }

    /**
     *  Lista as tabelas existentes no banco de dados.
     *
     *  @return array Lista de tabelas no banco de dados
     */
    public function listSources() {
        $this->sources = Cache::read('sources', '_easy_model_');
        if (empty($this->sources)) {
            $this->query("SHOW TABLES FROM {$this->config['database']}");
            while ($source = $this->result->fetch()) {
                $this->sources [] = $source[0];
            }
            Cache::write('sources', $this->sources, '_easy_model_');
        }
        return $this->sources;
    }

    /**
     *  Descreve uma tabela do banco de dados.
     *
     *  @param string $table Tabela a ser descrita
     *  @return array Descrição da tabela
     */
    public function describe($table) {
        $this->schema[$table] = Cache::read('describe', '_easy_model_');
        if (empty($this->schema[$table])) {
            $query = $this->query('SHOW COLUMNS FROM ' . $table);
            $columns = $this->fetchAll($query);
            $schema = array();

            foreach ($columns as $column) {
                $schema[$column->Field] = array(
                    'key' => $column->Key
                );
            }
            $this->schema[$table] = $schema;
            Cache::write('describe', $this->schema[$table], '_easy_model_');
        }

        return $this->schema[$table];
    }

}

?>
