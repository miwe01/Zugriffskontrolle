<?php

namespace App\Models;

use Exception;
use Illuminate\Database\Eloquent\Model;

use Predis;
//use mkorkmaz\src\RedisGraph;

use Redislabs\Module\RedisGraph\RedisGraph;
use Redislabs\Module\RedisGraph\GraphConstructor;
use Redislabs\Module\RedisGraph\Query;
use Redislabs\Module\RedisGraph\Node;
use Redislabs\Module\RedisGraph\Edge;

//define('AGGREGATION', array('avg', 'disj' , 'maj', 'conj'));
class SocialNetwork extends Model
{
    protected $connection;
    protected const DB = "SocialNetwork";
    protected const HOPS = 2;
    protected const labelUser = "person";
    protected const labelFile = "file";
    protected const trustTable = ["family"=>0.8, "friends"=>0.6, "others"=>0.4];
    
    
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
        catch(Predis\Connection\ConnectionException){
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
        catch(Predis\Connection\ConnectionException){
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
        catch(Predis\Connection\ConnectionException){
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
        catch(Predis\Connection\ConnectionException){
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
        catch(Predis\Connection\ConnectionException){
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
        catch(Predis\Connection\ConnectionException){
                throw new Exception("Fehler bei Verbindung");
        }
    }

    // Erstelle Kante User->User
    public function addEdgeUserUser($user1, $user2, $relation, $trust){
        if(empty($trust))
            $trust = self::trustTable[$relation] ?? 0;
        // Trenne $user, in firstname und lastname
        $a = explode(" ", $user1);
        $b = explode(" ", $user2);

        // Falls Kante noch nicht gibt, wird die Kante hinzugefügt
        $q1 = 
        'MATCH (p1:person {firstname: "' . $a[0] . '", lastname: "' . $a[1] . '"}), 
        (p2:person {firstname: "'. $b[0] . '", lastname: "' . $b[1] .'"}) 
        MERGE (p1)-[i:is {name: "' . $relation .'", trust: "' . $trust . '"}]->(p2)';

        $conn = $this->getConnection();
        try{
            $conn->query(new Query(self::DB, $q1));
        }
        catch(Predis\Connection\ConnectionException){
                throw new Exception("Fehler bei Verbindung");
        }

        return "Die Kante wurde hinzugefügt";
    }

    // Erstelle Kante User->File
    public function addEdgeUserFile($user, $filename, $stakeholder, $trust, $actions){
        // Trenne $user, in firstname und lastname
        $a = explode(" ", $user);
        // Trenne aktionen voneinander
        $actions = explode(",", $actions);

        $conn = $this->getConnection();
        
        for($i=0;$i<count($actions);$i++){
            $action = $actions[$i];
            // Falls Kante noch nicht gibt, wird die Kante hinzugefügt
            $q1 = 
            'MATCH (p1:person {firstname: "' . $a[0] . '", lastname: "' . $a[1] . '"}), 
            (f:file {name: "'. $filename .'"}) 
            MERGE (p1)-[i:is {stakeholder: "' . $stakeholder .'", trust: "' . $trust . '", action:"' . $action . '" }]->(f)';

            try{
                $conn->query(new Query(self::DB, $q1));
            }
            catch(Predis\Connection\ConnectionException){
                    throw new Exception("Fehler bei Verbindung");
            }
        }

         return "Die Kante wurde hinzugefügt";
    }
    
    // ------------------------------------Algo Methoden ------------------------------------------------------------------------

    // Überprüft ob Resource existiert, wenn ja ob Stakeholder dann true sonst gibt Aggregation zurück
    // in: $r = Resourcenmae, $u = Username
    // out: keine Resource gefunden | Benutzer ist Stakeholder (true) | Aggregation von Resource 
    public function StakeholderOrAggregation($r, $u){
        // q1 = Überprüft ob Resource existiert
        // q2 = Überprüft ob User Stakeholder ist von der Resource
        // q3 = Gib Owner von Resource $r zurück 
        $q1 = 'MATCH (f:file {name:"' . $r . '"}) RETURN f.name LIMIT 1';
        $q2 = 'MATCH (p:person {firstname:"' . $u[0] . '", lastname:"' . $u[1] .'"})-[h:have]->(f:file{name:"' . $r .'"}) RETURN f.name LIMIT 1';
        $q3 = 'MATCH (p:person)-[h:have]->(f:file{name:"' . $r . '"}) WHERE h.stakeholder = "owner" RETURN h.type LIMIT 1';

        $conn = $this->getConnection();
        try{   
            $result1 = $conn->query(new Query(self::DB, $q1));
            if(count($result1->getResultSet()) == 0)
                throw new Exception("Keine Resource gefunden");
            
            $result2 = $conn->query(new Query(self::DB, $q2));
            
            $result3 = $conn->query(new Query(self::DB, $q3));
        } 
        catch(Predis\Connection\ConnectionException){
            throw new Exception("Fehler bei Verbindung");
        }

        // Falls Benutzer Stakeholder ist
        if(count($result2->getResultSet()) == 1) return true; 
        
        // Gib Aggregation der Resource zurück
        if(count($result3->getResultSet()) > 0) return $result3->getResultSet()[0][0];
        
        throw new Exception("Keine Aggregation gefunden");
    }


    // Travseriert durch Graph von Stakeholder zu Benutzer und rechnet das Vertrauen zusammen
    // in: $r = Resourcenmae, $u = Username, $logAllow = Boolean logging, $log = Logbuch,
    // out: keine Resource gefunden | Benutzer ist Stakeholder (true) | Aggregation von Resource 
    // 1. Schritt: Bekomme alle Stakeholder von Resource r
    // 2. Schritt: Traversiere durch den Graph von jedem Stakeholder zu User der Zugriff haben möchte ($u)
    // 3. Schritt: Schreibe Stakeholder in Array pathtrust
    // 4. Schritt gebe Array mit allen Vertrauenslevel von Stakeholder zu User zurück
    public function path($r, $u, $logAllow, &$log){
        $pathTrust = [];
         // 1. Schritt
        $q = 'MATCH (p:person)-[h:have]->(f:file{name:"'. $r. '"}) RETURN p.firstname, p.lastname ORDER BY p.firstname';
        $matchQuery = new Query(self::DB, $q);
        $conn = $this->getConnection();
        try{ 
            $result = $conn->query($matchQuery);  
        } 
        catch(Predis\Connection\ConnectionException){
            throw new Exception("Fehler bei Verbindung");
        }

        $stakeholder = $result->getResultSet();
        if(count($stakeholder) == 0) throw new Exception("Keine Stakeholder gefunden");

        // 2. Schritt
        $log[] = "<h4>Pfad Traversierung</h4><ul>";        
        for($i=0;$i<count($stakeholder);$i++){
            // Bekomme Pfad von Stakeholder zu User (der Zugriff haben möchte)
            // Query summiert die Kanten der Knoten bis zum dem Benutzer der die Ressource angefragt hat zusammen
            // Gib den Pfad mit der höchsten Zahl zurück

            $q = 
            'MATCH (from:person{firstname:"' . $stakeholder[$i][0] . '", lastname:"' . $stakeholder[$i][1] . '"}),
            (to:person{firstname:"' . $u[0] . '", lastname:"' . $u[1] . '"})
            WITH from, to MATCH path = (from)-[:is*1..' . self::HOPS . ']->(to) 
            WITH REDUCE (total = 1, r in relationships(path) | total * r.distance) 
            as cost, path ORDER BY cost DESC RETURN cost LIMIT 1';
    
            $matchQuery = new Query(self::DB, $q);
            try{
                $result = $conn->query($matchQuery);
            } 
            catch(Predis\Connection\ConnectionException){
                throw new Exception("Fehler bei Verbindung");
            }

            $resultSet = $result->getResultSet();

            // 3. Schritt
            if(count($resultSet) != 0)
                $pathTrust[] = $resultSet[0][0];
            
            else // wenn keine Pfad von Stakeholder zu To gefunden wurde, schreibe 0 in array
                $pathTrust[] = 0;
            
            if($logAllow){
                $log[] = "<li>Pfad von ". $stakeholder[$i][0]  . " " . $stakeholder[$i][1]  ." -> " . $pathTrust[$i] . "</li>";
            }
        }
        if($logAllow){ $log[] = "</ul>"; }
        
        // 4. Schritt
        return $pathTrust;
    }

    // 1. Schritt: bekomme Vertrauenslevel & erlaubte Aktionen von allen Stakeholdern von Ressource r
    // 2. Schritt: Array wird in Trust, Stakeholdername und Aktionen unterteilt
    // 3. Schritt: vergleiche Vertrauenslevel von Stakeholder zu Ressource mit
    // summierten Vertrauenslevel von Pfadvertrauen zu dem Benutzer der angefragt hat
    // Wenn Aggregation average ist, wird anders Durschnittsvertrauen berechnet
    // 4. Schritt: überprüfe ob Aktion von Stakeholder unterstützt wird, wenn nicht geben false zurück
    // 5. return die Aktion die ausgeführt werden darf und 
    // das policy Array, true bedeutet Stakeholder sein Vertrauenslevel ist erfüllt, false heisst es ist nicht erfüllt
    public function stakeholderActions($r, $aggregation, $pathTrust, $action, &$log, $logAllow){
        $policy = [];
        $actions = [];
        $i = 0;

        //1. Schritt
        // Gibt Vertrauen und Aktionen die erlaubt sind von allen Stakeholdern
        $q = 
        'MATCH (p:person)-[h:have]->(f:file{name:"' . $r .'"}) 
         RETURN h.trust, collect(DISTINCT h.action), p.firstname, p.lastname 
         ORDER BY p.firstname';
        $matchQuery = new Query(self::DB, $q);
        
        try{
            $result = $this->getConnection()->query($matchQuery);
        } 
        catch(Predis\Connection\ConnectionException){
            throw new Exception("Fehler bei Verbindung");
        }

        // Stkh = Stakeholders
        $StkhTrustAndActions = $result->getResultSet();

        if(count($StkhTrustAndActions) == 0) throw new Exception("Fehler bei Query");
        if(count($pathTrust) != count($StkhTrustAndActions)) throw new Exception("Fehler aufgetreten: Pfad Array und Vetrtauen Stakeholder nicht gleich lang");;
        
        if($logAllow) $log[] = "<h4>Erlaubte Aktionen</h4><ol>";

        // 2. Schritt
        for($i=0;$i<count($StkhTrustAndActions);$i++){
            $trust[] = $StkhTrustAndActions[$i][0]; // alle Vertrauenslevel von Stakeholder
            $name = $StkhTrustAndActions[$i][2] . " " . $StkhTrustAndActions[$i][3]; // Name von Stakeholder zu Resource
            
            // alle erlaubten Aktionen von Stakeholdern
            //Gibt statt Array => [] Resultat als String zurück
            $array = explode(',', str_replace(array('[', ']'), '', $StkhTrustAndActions[$i][1]));
            
            for($j=0;$j<count($array);$j++){
                if($logAllow) $log[] = "<li>". $name . ": ". $array[$j] ." </li>";

                // Füge Aktion zusammen in ein Array
                $actions[] = $array[$j];
            }
               
        }
        $actions = array_unique($actions);

        if($logAllow) $log[] = "</ol><h4>Vertrauenslevel</h4><ol>";
        // 3. Schritt
        // Wenn Aggregation average ist, wird anders berechnet wie bei anderen Aggregationen
        if ($aggregation == AGGREGATION[0]){
            $avg = array_sum($pathTrust)/count($pathTrust);
            $x = $pathTrust[$i] > $avg;
        }
        else // andere Aggregation als avg zB conj, disj...
            $x = $trust;

        // 3. Schritt: vergleiche Vertrauenslevel von Stakeholder zu Ressource mit
        // summierten Vertrauenslevel von Pfadvertrauen zu dem Benutzer der angefragt hat
        for($i=0;$i<count($pathTrust);$i++){
            if($pathTrust[$i] > $x[$i])
                $policy[] = "true";
            else
                $policy[] = "false";
            
            if($logAllow) $log[] = "<li>Stakeholder Vertrauen von Resource -> "  . $x[$i] . ". Resultat: " . $policy[$i] ."</li>";
        }
        if($logAllow) $log[] = "</ol>";
   
        // 4. Schritt
        if(!in_array($action, $actions)){
            if($logAllow) $log[] = "<p>Die Aktion hatte nicht jeder Stakeholder zur Verfügung</p>";
            $action = "false";
        }
            
        // 5. Schritt
        return array($action, $policy);
    }

}