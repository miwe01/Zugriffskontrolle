<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

use Predis;

use Redislabs\Module\RedisGraph\RedisGraph;
use Redislabs\Module\RedisGraph\GraphConstructor;
use Redislabs\Module\RedisGraph\Query;

class SocialNetwork_api extends Model
{
    use HasFactory;

    protected $connection;
    protected const DB = "SocialNetwork";
    protected const trustTable = ["family"=>0.8, "friends"=>0.6, "others"=>0.4];

    protected const labelUser = "person";
    protected const labelFile = "file";
    
    // Konstruktor
    public function __construct() {
        try{
            $redisClient = new Predis\Client();
            $this->connection = RedisGraph::createWithPredis($redisClient);
        }
        catch(Predis\Connection\ConnectionException $e) {
            return false;
       }
    }

    public function getConnection(){
        return $this->connection;
    }

    public function createGraph(){
        return new GraphConstructor(self::DB);
    }


    // Bekomme alle User
    public function allUsers(){
        $q1 = 'MATCH (p:person) RETURN p.firstname, p.lastname';
        $conn = $this->getConnection();
        try{
            $result1 = $conn->query(new Query(self::DB, $q1));
            return $result1->getResultSet();
        }
        catch(Predis\Connection\ConnectionException $e){
            throw new Exception("Fehler bei Verbindung");
        }
    }

    // Bekomme alle Ressourcen
    public function allResources(){
        $q1 = 'MATCH (f:file) RETURN f.name';
        $conn = $this->getConnection();
        try{
            $result1 = $conn->query(new Query(self::DB, $q1));
            return $result1->getResultSet();
        }
        catch(Predis\Connection\ConnectionException $e){
                throw new Exception("Fehler bei Verbindung");
        }
    }

    // Füge Benutzer hinzu
    public function addUser($firstname, $lastname, $age){
        $p = ['firstname' => $firstname, 'lastname' => $lastname, 'age'=> $age];

        $u = Node::createWithLabel(self::labelUser)->withProperties($p);
        $g = $this->createGraph();
        $g->addNode($u);

        $commitQuery = $g->getCommitQuery();
        
        try{
            $this->getConnection()->commit($commitQuery);
        }
        catch(Predis\Connection\ConnectionException $e){
                throw new Exception("Fehler bei Verbindung");
        }

        return "Der Knoten wurde hinzugefügt";
    }

    // Füge Datei hinzu
    public function addFile($name){
        $p = ['name' => $name];

        $f = Node::createWithLabel(self::labelFile)->withProperties($p);
        $g = $this->createGraph();
        $g->addNode($f);

        $commitQuery = $g->getCommitQuery();
        
        try{
            $this->getConnection()->commit($commitQuery);
        }
        catch(Predis\Connection\ConnectionException $e){
                throw new Exception("Fehler bei Verbindung");
        }

        return "Die Datei wurde hinzugefügt";
    }

    // Gebe bestimmten Nutzer zurück
    public function getUser($firstname, $lastname){
        $q1 = 'MATCH (p:person {firstname:"' . $firstname . '", lastname:"' . $lastname .'"}) RETURN p.firstname, p.lastname, p.age LIMIT 1';
        $conn = $this->getConnection();
        try{
            $result1 = $conn->query(new Query(self::DB, $q1));
            return $result1->getResultSet()[0];
        }
        catch(Predis\Connection\ConnectionException $e){
                throw new Exception("Fehler bei Verbindung");
        }
    }

    // Gebe bestimmte Datei zurück
    public function getFile($name){
        $q1 = 'MATCH (f:file {name:"' . $name . '"}) RETURN f LIMIT 1';
        $conn = $this->getConnection();
        try{
            $result1 = $conn->query(new Query(self::DB, $q1));
            return $result1->getResultSet();
        }
        catch(Predis\Connection\ConnectionException $e){
                throw new Exception("Fehler bei Verbindung");
        }
    }

    // Erstelle Kante User->User, wenn schon eine existiert lösche die vorherige Kante
    public function addEdgeUserUser($user1, $user2, $relation, $trust){
        if(empty($trust))
            $trust = self::trustTable[$relation] ?? 0;
        // Trenne $user, in firstname und lastname

        $a = explode(" ", $user1);
        $b = explode(" ", $user2);

        // $q1 = 
        'MATCH (p1:person {firstname: "' . $a[0] . '", lastname: "' . $a[1] . '"})
        -[i:is]->
        (p2:person {firstname: "'. $b[0] . '", lastname: "' . $b[1] .'"}) DELETE i';

        // Falls Kante noch nicht gibt, wird die Kante hinzugefügt
        $q2 = 
        'MATCH (p1:person {firstname: "' . $a[0] . '", lastname: "' . $a[1] . '"}), 
        (p2:person {firstname: "'. $b[0] . '", lastname: "' . $b[1] .'"}) 
        MERGE (p1)-[i:is {name: "' . $relation .'", distance: ' . $trust . '}]->(p2)';

        // $q2 = 
        // 'MATCH (p1:person {firstname: "' . $a[0] . '", lastname: "' . $a[1] . '"})
        // -[i:is]-> 
        // (p2:person {firstname: "'. $b[0] . '", lastname: "' . $b[1] .'"}) 
        // SET i.name = "' . $relation . '", i.distance = ' . $trust;


       
        // $conn = $this->getConnection();
        // try{
        //     $conn->query(new Query(self::DB, $q1));
        // }
        // catch(Predis\Connection\ConnectionException){
        //         throw new Exception("Fehler bei Verbindung");
        // }

        $conn = $this->getConnection();
        try{
            $conn->query(new Query(self::DB, $q2));
        }
        catch(Predis\Connection\ConnectionException $e){
                throw new Exception("Fehler bei Verbindung");
        }


        //dd($q2);
        return "Die Kante wurde hinzugefügt";
    }

    // Erstelle Kante User->File, wenn schon einer Owner ist, mache den nächsten Coowner
    public function addEdgeUserFile($user, $filename, $stakeholder, $trust, $actions, $agg){
        $change = false; // wenn owner zu coowner umgeändert wird, weil es schon einen owner gibt

        // Trenne $user, in firstname und lastname
        $a = explode(" ", $user);
        // Trenne aktionen voneinander
        $actions = explode(",", $actions);

        // Überprüfe ob Stakeholder owner schon gibt wenn ja mache ihn coowner
        $q1 = 'MATCH (p:person)-[h:have]->(f:file{name:"' . $filename . '"}) WHERE h.stakeholder = "owner" RETURN p.firstname, p.lastname LIMIT 1';
        $conn = $this->getConnection();

        try{
            $conn->query(new Query(self::DB, $q1));
            $result = $conn->query(new Query(self::DB, $q1))->getResultSet();
            if(count($result) > 0 && $result[0][0] != $a[0] && $result[0][1] != $a[1]){
                $stakeholder = "coowner";
                $change = true;
            } 
        }
        catch(Predis\Connection\ConnectionException $e){
                throw new Exception("Fehler bei Verbindung");
        }
        
        // Erstelle Kante für jede Aktion zu Resource
        for($i=0;$i<count($actions);$i++){
            $action = $actions[$i];
            // Falls Kante noch nicht gibt, wird die Kante hinzugefügt¨

            if($stakeholder == "coowner"){
                $q2 = 
                'MATCH (p1:person {firstname: "' . $a[0] . '", lastname: "' . $a[1] . '"}), 
                (f:file {name: "'. $filename .'"}) 
                MERGE (p1)-[h:have {stakeholder: "' . $stakeholder .'", trust: "' . $trust . '", action:"' . $action . '" }]->(f)';
            }
            else{
                $q2 = 
                'MATCH (p1:person {firstname: "' . $a[0] . '", lastname: "' . $a[1] . '"}), 
                (f:file {name: "'. $filename .'"}) 
                MERGE (p1)-[h:have {stakeholder: "' . $stakeholder .'", trust: "' . $trust . '", action:"' . $action . '", type: "' . $agg .'"}]->(f)';
            }
            

            try{
                $conn->query(new Query(self::DB, $q2));
            }
            catch(Predis\Connection\ConnectionException $e){
                    throw new Exception("Fehler bei Verbindung");
            }
        }
        if($change)
            return "Gibt schon Besitzer für Datei, Kante wurde als Mitbesitzer hinzugefügt";
         return "Die Kante wurde hinzugefügt";
    }
}
