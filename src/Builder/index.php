<?php

namespace PerficientTest\Builder;

/**
 * La interfaz Builder declara un conjunto de métodos para ensamblar una consulta SQL.
 * 
 * Todos los pasos de construcción devuelven el objeto constructor actual para permitir el
 * encadenamiento: $builder->select(...)->where(...)
 */
interface SQLQueryBuilder
{
    public function select(string $table, array $fields): SQLQueryBuilder;

    public function where(string $field, string $value, string $operator = '='): SQLQueryBuilder;

    public function limit(int $start, int $offset): SQLQueryBuilder;

    public function getSQL(): string;
}

/**
 * Cada Concrete Builder corresponde a un dialecto SQL específico y puede implementar los pasos
 * del constructor de forma un poco diferente a los demás.
 * 
 * Este Concrete Builder puede crear consultas SQL compatibles con MySQL.
 */
class MySQLQueryBuilder implements SQLQueryBuilder
{
    protected $query;

    protected function reset(): void
    {
        $this->query = new \stdClass();
    }

    /**
     * Crea una consulta SELECT base.
     */
    public function select(string $table, array $fields): SQLQueryBuilder
    {
        $this->reset();
        $this->query->base = "SELECT " . implode(", ", $fields) . " FROM " . $table;
        $this->query->type = 'select';

        return $this;
    }

    /**
     * Agrega una condición WHERE.
     */
    public function where(string $field, string $value, string $operator = '='): SQLQueryBuilder
    {
        if (!in_array($this->query->type, ['select', 'update', 'delete'])) {
            throw new \Exception("WHERE can only be added to SELECT, UPDATE or DELETE");
        }
        $this->query->where[] = "$field $operator '$value'";

        return $this;
    }

    /**
     * Agrega una restricción LÍMITE.
     */
    public function limit(int $start, int $offset): SQLQueryBuilder
    {
        if (!in_array($this->query->type, ['select'])) {
            throw new \Exception("LIMIT can only be added to SELECT");
        }
        $this->query->limit = " LIMIT " . $start . ", " . $offset;

        return $this;
    }

    /**
     * Obtenga la cadena de consulta final.
     */
    public function getSQL(): string
    {
        $query = $this->query;
        $sql = $query->base;
        if (!empty($query->where)) {
            $sql .= " WHERE " . implode(' AND ', $query->where);
        }
        if (isset($query->limit)) {
            $sql .= $query->limit;
        }
        $sql .= ";";
        return $sql;
    }
}

/**
 * Este Concrete Builder es compatible con PostgreSQL. Si bien Postgres es muy similar a
 * MySQL, todavía tiene varias diferencias. Para reutilizar el código común, lo ampliamos
 * desde el constructor MySQL, mientras anulamos algunos de los pasos de construcción.
 * 
 */
class PostgresQueryBuilder extends MySQLQueryBuilder
{
    /**
     * Entre otras cosas, PostgresSQL tiene una sintaxis LIMIT ligeramente diferente.
     */
    public function limit(int $start, int $offset): SQLQueryBuilder
    {
        parent::limit($start, $offset);

        $this->query->limit = " LIMIT " . $start . " OFFSET" . $offset;

        return $this;
    }
}

/**
 * Tenga en cuenta que el código del cliente utiliza el objecto constructor directamente.
 * En este caso no es necesaria una clase Director designada, porque el código del cliente
 * necesita consultas diferentes casi cada vez, por lo que la secuencia de los pasos de
 * construcción no se puede reutilizar fácilmente.
 * 
 * Dado que todos nuestros creadores de consultas crean productos del mismo tipo (que es
 * una cadena), podemos interactuar con todos los creadores utilizando su interfaz común.
 * Posteriormente, si implementamos una nueva clase Builder, podremos pasar su instancia
 * al código del cliente existente sin romperlo gracias a la interfaz SQLQueryBuilder.
 */
function clientCode(SQLQueryBuilder $queyBuilder)
{
    $query = $queyBuilder
        ->select("users", ["name", "email", "password"])
        ->where("age", 18, ">")
        ->where("age", 30, "<")
        ->limit(10, 20)
        ->getSQL();

    echo $query;
}

echo "Testing MySQL query builder:\n";
clientCode(new MySQLQueryBuilder());

echo "\n\n";

echo "Testing PostgresSQL query builder:\n";
clientCode(new PostgresQueryBuilder());
