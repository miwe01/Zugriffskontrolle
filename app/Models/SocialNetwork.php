<?php

namespace App\Models;

use Exception;
use Illuminate\Database\Eloquent\Model;

use Predis;

use Redislabs\Module\RedisGraph\RedisGraph;
use Redislabs\Module\RedisGraph\GraphConstructor;
use Redislabs\Module\RedisGraph\Query;
//use Redislabs\Module\RedisGraph\Node;
//use Redislabs\Module\RedisGraph\Edge;

//define('AGGREGATION', array('avg', 'disj' , 'maj', 'conj'));
class SocialNetwork extends Model
{
    protected $connection;
    protected const DB = "SocialNetwork";
    protected const HOPS = 3;
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
        catch(Predis\Connection\ConnectionException $e){
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
        catch(Predis\Connection\ConnectionException $e){
            throw new Exception("Fehler bei Verbindung");
        }

        $stakeholder = $result->getResultSet();
        if(count($stakeholder) == 0) throw new Exception("Keine Stakeholder gefunden");

        // 2. Schritt
        if($logAllow) $log[] = "<h3>Pfad Traversierung</h3>";

       
        for($i=0;$i<count($stakeholder);$i++){
            // Bekomme Pfad von Stakeholder zu User (der Zugriff haben möchte)
            // Query summiert die Kanten der Knoten bis zum dem Benutzer der die Ressource angefragt hat zusammen
            // Gib den Pfad mit der höchsten Zahl zurück
            
            
            $q = 
            'MATCH (from:person{firstname:"' . $stakeholder[$i][0] . '", lastname:"' . $stakeholder[$i][1] . '"}),
            (to:person{firstname:"' . $u[0] . '", lastname:"' . $u[1] . '"})
            WITH from, to MATCH path = (from)-[:is*1..' . self::HOPS . ']->(to) 
            WITH REDUCE (total = 1, r in relationships(path) | total * r.distance) 
            as cost, path ORDER BY cost DESC RETURN cost, path LIMIT 1';
    
            
            $matchQuery = new Query(self::DB, $q);
            try{
                $result = $conn->query($matchQuery);
            } 
            catch(Predis\Connection\ConnectionException $e){
                throw new Exception("Fehler bei Verbindung");
            }

            $resultSet = $result->getResultSet();

            // 3. Schritt
            if(count($resultSet) != 0)
                $pathTrust[] = $resultSet[0][0];
            
            else // wenn keine Pfad von Stakeholder zu To gefunden wurde, schreibe 0 in array
                $pathTrust[] = 0;
            
            if($logAllow){
                $log[] = "<li>Pfad von ". $stakeholder[$i][0]  . " " . $stakeholder[$i][1]  ." -> <b>" . $pathTrust[$i] . "</b></li>";
                
                if(!empty($resultSet))
                    $log[] = "<li>Knoten und Pfade: " .  $resultSet[0][1] . "</li>";
            }
        }
 
       
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
        $actionFehler = false;
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
        catch(Predis\Connection\ConnectionException $e){
            throw new Exception("Fehler bei Verbindung");
        }

        // Stkh = Stakeholders
        $StkhTrustAndActions = $result->getResultSet();


        if(count($StkhTrustAndActions) == 0) throw new Exception("Fehler bei Query");
        // if(count($pathTrust) != count($StkhTrustAndActions)) throw new Exception("Fehler aufgetreten: Pfad Array und Vetrtauen Stakeholder nicht gleich lang");

        if($logAllow) $log[] = "<h3>Erlaubte Aktionen von Stakeholder</h3>";

        // 2. Schritt
        
        for($i=0;$i<count($StkhTrustAndActions);$i++){
            $trust[] = $StkhTrustAndActions[$i][0]; // alle Vertrauenslevel von Stakeholder
            $name = $StkhTrustAndActions[$i][2] . " " . $StkhTrustAndActions[$i][3]; // Name von Stakeholder zu Resource
            
            // alle erlaubten Aktionen von Stakeholdern
            //Gibt statt Array => [] Resultat als String zurück   
            $sanitize = str_replace(array('[', ']'), '', $StkhTrustAndActions[$i][1]);
            if($logAllow) $log[] = "<li>". $name . ": ". $sanitize ." </li>";

            // in Array umwandeln und dann in Array von Array

            $sanitize = str_replace(" ", "", $sanitize);
            $sanitize = explode(',', $sanitize);
            $actions[] = $sanitize;
           
               
        }
        // dd($log);
        // dd($actions);
        // $actions = array_unique($actions);
        

        if($logAllow) $log[] = "<h3>Überprüfe ob Benutzer Zugriff hat</h3>Überprüfe ob Pfad Vertrauen > Stakeholder Vertrauen";
        // 3. Schritt
        // Wenn Aggregation average ist, wird anders berechnet wie bei anderen Aggregationen
       
        $avg = false;
        if ($aggregation == AGGREGATION[0]){
            $avg = array_sum($trust)/count($trust);

            if($logAllow) $log[] = "<p>Durchschnittlicher Stakeholder-Vertrauen: <b>" . $avg . "</b></p>";
        }

        // 3. Schritt: vergleiche Vertrauenslevel von Stakeholder zu Ressource mit
        // summierten Vertrauenslevel von Pfadvertrauen zu dem Benutzer der angefragt hat
      
        
        for($i=0;$i<count($pathTrust);$i++){
            $name = $StkhTrustAndActions[$i][2] . " " . $StkhTrustAndActions[$i][3];
            $trust[] = $StkhTrustAndActions[$i][0];

            if($avg)
                $x = $avg;
            else
                $x = $trust[$i];
            

            if($pathTrust[$i] > $x)
                $policy[] = "true";
            else
                $policy[] = "false";

            if($logAllow) 
                $log[] = "<p><b>" . $name . "</b><p>
                          <p>Stakeholder-Vertrauen: -> "  . $trust[$i] . "<br>
                          Pfad-Vertrauen " . $pathTrust[$i] . "<br>
                          Ist Pfad-Vertrauen ". $pathTrust[$i] . " > " . $x . " Stakeholder-Vertrauen? <b>" . $policy[$i] ."</b></p>";
                          

            // 4. Schritt
            if(!in_array($action, $actions[$i]))           
                $actionFehler = true;
        }

        if($actionFehler){
            if($logAllow) $log[] = "<p>Die Aktion <b>" . $action . "</b> unterstützt <b>nicht</b> jeder Stakeholder</p>";
            $action = "false";
        }
            
   
        
            
        // 5. Schritt
        return array($action, $policy);
    }

}