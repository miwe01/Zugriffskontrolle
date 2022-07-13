<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

use Predis;
//use mkorkmaz\src\RedisGraph;

use Redislabs\Module\RedisGraph\RedisGraph;
use Redislabs\Module\RedisGraph\Node;
use Redislabs\Module\RedisGraph\Edge;
use Redislabs\Module\RedisGraph\GraphConstructor;
use Redislabs\Module\RedisGraph\Query;

class SocialNetwork1 extends Model
{
    // use HasFactory;
    protected $connection;
    protected const DB = "Faker2";
    
    public function getConnection(){
        return $this->connection;
    }
    // public function setConnection($con){
    //     $this->connection = $con;
    // }
    
    public function __construct() {
        try{
            $redisClient = new Predis\Client();
            $this->connection = RedisGraph::createWithPredis($redisClient);
        }
        catch(Predis\Connection\ConnectionException $e) {
            return false;
       }
        
    }

    public function createGraph($name){
        return new GraphConstructor($name);
    }

    // Überprüft ob Resource existiert
    // in: Resource
    // out: boolean
    public function checkResource($r){

        if(empty($this->getConnection()))
            exit("Error: Fehler mit der Verbindung");
        
        //exit(print_r($this->getConnection()));

        $query = 'MATCH (f:file {name:"' . $r . '"}) RETURN f.name';
        
        $matchQuery = new Query(self::DB, $query);

        try{
            $result = $this->getConnection()->query($matchQuery);
        } 
        catch(Predis\Connection\ConnectionException){
            return false;
        }
        
        
        if(count($result->getResultSet()) > 0){
            return true;
        }
        return false;
    }

    // Überprüft ob Stakeholder von Resource r ist
    // in: User $u und Resource $r
    // out: boolean
    public function isStakeholder($u, $r){
        if(empty($this->getConnection()))
            exit("Error: Fehler mit der Verbindung");

       $query = 'MATCH (p:person {firstname:"' . $u[0] . '", lastname:"' . $u[1] .'"})-[h:have]->(f:file{name:"' . $r .'"}) RETURN f.name LIMIT 1';
       
       $matchQuery = new Query(self::DB, $query);

       try{
        $result = $this->getConnection()->query($matchQuery);
    } 
    catch(Predis\Connection\ConnectionException){
        return false;
    }


       if(count($result->getResultSet()) > 0){
           return true;
       }
       return false;
    }

    // Gibt Liste von Stakeholdern der Resource r zurück
    // in: Resource $r
    // out: Liste von Stakeholdern
    public function Stakeholder($r){

        if(empty($this->getConnection()))
        exit("Error: Fehler mit der Verbindung");

        $query = 'MATCH (p:person)-[h:have]->(f:file{name:"'. $r. '"}) RETURN p.firstname, p.lastname';

        $matchQuery = new Query(self::DB, $query);

        try{
            $result = $this->getConnection()->query($matchQuery);
        } 
        catch(Predis\Connection\ConnectionException){
            return false;
        }

        
        return $result->getResultSet();
    }

    // Gibt Aggregation zurück von Ressource (avg, conj ...)
    // in: Ressource $r
    // out: Aggregation
    public function checkAggregation($r){
        if(empty($this->getConnection()))
            exit("Error: Fehler mit der Verbindung");   

        $query = 'MATCH (p:person)-[h:have]->(f:file{name:"' . $r . '"}) WHERE h.stakeholder = "owner" RETURN h.type LIMIT 1';

        $matchQuery = new Query(self::DB, $query);

        try{
            $result = $this->getConnection()->query($matchQuery);
        } 
        catch(Predis\Connection\ConnectionException){
            return false;
        }

        $resultSet = $result->getResultSet();


        if(count($resultSet) > 0)
            return $resultSet[0][0];

        return false;

    }

    // Gibt gültige Aktionen von Stakeholdern zurück auf Ressource r (read, write, like...)
    // in: Ressource r
    // out: Array von gültigen Aktionen
    public function stakeholderActions($r){
        if(empty($this->getConnection()))
        exit("Error: Fehler mit der Verbindung");


        $query = 'MATCH (p:person)-[h:have]->(f:file{name:"' . $r .'"}) RETURN p.name, collect(DISTINCT h.action)';

        $matchQuery = new Query(self::DB, $query);

        try{
            $result = $this->getConnection()->query($matchQuery);
        } 
        catch(Predis\Connection\ConnectionException){
            return false;
        }


        if(count($result->getResultSet()) > 0){
            return $result->getResultSet();
        }
        return false;
    }

    //****** Ändere wahrscheinlich noch um  mit stakeholder array, werde wahrscheinlich selber in Funktion die Stakeholder fragen ********/
    //************************************* */

    // Gibt Vertrauen von Stakeholder der Resource r zurück
    // in: array von Stakeholder
    // out: Vertrauenslevel der Stakeholder
    public function StakeholderTrust($stakeholder, $r){
        if(empty($this->getConnection()))
        exit("Error: Fehler mit der Verbindung");

        $a = [];
        
        for($i=0;$i<count($stakeholder);$i++){
            // Bekomme Vertrauen von Stakeholder zu Resource r

            $query = 'MATCH (p:person{firstname:"'. $stakeholder[$i][0] . '", lastname:"'. $stakeholder[$i][1] . '"})-[h:have]->(f:file{name:"' . $r .'"}) RETURN h.trust LIMIT 1';
            $matchQuery = new Query(self::DB, $query);

            try{
                $result = $this->getConnection()->query($matchQuery);
            } 
            catch(Predis\Connection\ConnectionException){
                return false;
            }

            $resultSet = $result->getResultSet();

            if(count($resultSet) > 0){
                $a[] = $resultSet[0][0];
            }
            
        }
        return $a;
    }

    // in: array von Stakeholdern von Ressource
    //     Knoten der Zugriff auf Dokument gerne möchte
    // out: array [0] = Pfad den der Algo durch den Graph ging
    //            [1] = Kosten vom Pfad
    public function path($stakeholder, $to){
        if(empty($this->getConnection()))
        exit("Error: Fehler mit der Verbindung");

        $a = [];

        for($i=0;$i<count($stakeholder);$i++){

            
            // Bekomme Pfad von Stakeholder zu User to (der Zugriff haben möchte)
            $query = 
            'MATCH (from:person{firstname:"' . $stakeholder[$i][0] . '", lastname:"' . $stakeholder[$i][1] . '"}),
            (to:person{firstname:"' . $to[0] . '", lastname:"' . $to[1] . '"})
            WITH from, to MATCH path = (from)-[:is*1..3]->(to) 
            WITH REDUCE (total = 1, r in relationships(path) | total * r.distance) 
            as cost, path ORDER BY cost RETURN cost LIMIT 1';
    


            $matchQuery = new Query(self::DB, $query);
            try{
                $result = $this->getConnection()->query($matchQuery);
            } 
            catch(Predis\Connection\ConnectionException){
                return false;
            }

            $resultSet = $result->getResultSet();
            
            if(count($resultSet) != 0){
                $a[] = $resultSet[0][0];
            }
            else{
                $a[] = 0;
            } 
           
        }
        
        return $a;
    }

}