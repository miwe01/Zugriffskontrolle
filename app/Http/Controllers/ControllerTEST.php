<?php

namespace App\Http\Controllers;

use App\Models\SocialNetwork1;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Foundation\Bus\DispatchesJobs;
use Illuminate\Foundation\Validation\ValidatesRequests;
use Illuminate\Routing\Controller as BaseController;
use Illuminate\Http\Request;
// use kjdev\src\Redis\Graph;
// use Predis\src\Client;
use Predis;
use kjdev;
use Redis\Graph;
use Redis\Graph\Node;
use Redis\Graph\Edge;
use Illuminate\Support\Facades\Redis;

// Alle Aggregationswerte
// ACHTUNG: im Moment ist Reihenfolge wichtig, bei Änderung kann es zu Fehlern führen
define('AGGREGATION', array('avg', 'disj' , 'maj', 'conj'));
class Controller extends BaseController
{
    use AuthorizesRequests, DispatchesJobs, ValidatesRequests;

    // Überprüft ob User Aktion ausführen darf (jeder Stakeholder muss die Aktion erlauben sonst false)
    // in: $action zB read, ...
    //     $actions = Array von allen Aktionen
    // out: gültige Aktion | false 
    private function actionAllowed($action, $actions){
      for($i=0; $i<count($actions);$i++){
        if(!str_contains($action, $actions[$i][1])) 
          return false;
      }
      return $action;
    }

    // Schritt 1: Avgerage Case, nimmt Durschnitt von Pfad der durschlaufen wurde
    // Schritt 2: überprüft ob höher oder kleiner als Vertrauen von Stakeholder zu Resource ist
    // Schritt 3: Wenn mindestens ein "Pfadvertrauen" kleiner ist, wird überprüft ob Mehrheit true oder false ist
    //
    private function averageAggregation($actionAllow, $trust_values, $trustStakeholder, $time_pre){
      $policy = [];

      if(count($trust_values)==0) exit("Fehler: Keine Vertrauenslevel von Stakeholder gefunden");
      if(count($trustStakeholder)==0) exit("Fehler: Keine Vertrauenslevel von Stakeholder gefunden");

      // Schritt 1
      $avg = array_sum($trust_values)/count($trust_values);

      for($i=0; $i<count($trust_values);$i++){
        // Schritt 2
        if($trust_values[$i] > $avg)
          $policy[] = true;
        else
          $policy[] = false;
      }


      
      $time_post = microtime(true);
      $s = ($time_post - $time_pre);
      echo "Dauer: " . $s . "s";

      // Schritt 3
      if(in_array(false, $policy)){
      //source: https://stackoverflow.com/questions/5945199/counting-occurrence-of-specific-value-in-an-array-with-php
        $occurences = count(array_filter($policy, function ($n) { return $n == true; }));

        $aboveAverage = $occurences > round( (count( $trust_values )-1) /2);
        if($aboveAverage){
          if(!$actionAllow) exit("Du darfst nur auf die Ressource sehen (view)");
          exit("Du darfs auf die Ressource " . $actionAllow);
        }

        exit("Du hast keinen Zugriff, da dein Vertrauenslevel zu niedrig ist");
        
      }
      
    }

    // Überprüft ob User Zugriff auf Resource hat
    // in
    // out
    private function otherAggregations($actionAllow, $trust_values, $trustStakeholder, $aggregation, $time_pre){
      $policy = [];

      print_r($trust_values);
      echo "<br>";
      print_r($trustStakeholder);

      for($i=0; $i<count($trust_values);$i++){
        if($trust_values[$i] > $trustStakeholder[$i])
          $policy[] = true;
        else
          $policy[] = false;
      }

      $case1 = $aggregation == "conj" && !in_array(false, $policy); // alle Bedingungen müssen erfüllt sein
      $case2 = $aggregation == "disj" && in_array(true, $policy); // mindestens eine muss erfüllt sein
 
      // über die Hälfte muss erfüllt sein
      $case3 = $aggregation == "maj" && 
        count( array_filter( $policy, function ($n) { return $n == true; } )) > round( (count( $trust_values )-1) /2);

      $time_post = microtime(true);
      $s = ($time_post - $time_pre);

      echo "Dauer: " . $s . "s";
      if($case1 || $case2 || $case3){
        if(!$actionAllow) exit("Du darfst nur auf die Ressource sehen (view)");  
          exit("Du darfst auf die Ressource " . $actionAllow);
      }
        exit("Du hast keinen Zugriff, da dein Vertrauenslevel zu niedrig ist");
    }



  // Hauptfunktion: Überprüft ob Zugriff auf Ressource und ausgewählte Aktion möglich ist
  //in
  //out
  public function evaluatePolicy(Request $req){
    if (!$req->input('firstname') || !$req->has('lastname') || !$req->has('file') || !$req->has('action')){
      return "Fehler: Parameter falsch";
    }
    $time_pre = microtime(true);

    // $Y = $req->input('firstname') . ' ' . $req->input('lastname');¨
    // $name = explode(" ", $Y);

    $name = array($req->input('firstname'), $req->input('lastname'));
    $r = $req->input('file');
    $action = $req->input('action');


    $model = new SocialNetwork1();
    $conn = $model->getConnection();
    
    // Wenn Resource nicht in Graph ist
    if(!$model->checkResource($r)) { exit("Resource gibt es nicht!"); } 

    // Überprüfe ob Stakeholder?
    $stakeholder = $model->Stakeholder($r);
    if($model->isStakeholder($name,$r) != 0){ exit("Du bist Stakeholder"); }


    // if (con1 > 0 && cond2 > 0 && con3)


    // Überprüfe ob Aggregation existiert
    $aggregation = ($model->checkAggregation($r));

    if(!$aggregation) { exit("Fehler: keine Aggregation gefunden");}
    echo $aggregation;
    // Pfad durch Graph & Überprüfe ob überhaupt existiert
    $trust_values = $model->path($stakeholder, $name, $r);
    if(count($trust_values)==0) exit("Fehler: Keine Vertrauenslevel von Stakeholder Pfad gefunden");


    // gültige Aktionen von Stakeholdern auf r zB view, like...
    $actions = $model->stakeholderActions($r);

    $trustStakeholder = $model->StakeholderTrust($stakeholder, $r);
    if(count($trustStakeholder)==0) exit("Fehler: Keine Vertrauenslevel von Stakeholder gefunden");

    $actionAllow = $this->actionAllowed($action, $actions);

    // Wenn average Aggregation
    if($aggregation == AGGREGATION[0])
      $this->averageAggregation($actionAllow, $trust_values, $trustStakeholder, $time_pre);
    
    // Andere Aggregationen disj, avg, conj
    else if(in_array($aggregation, AGGREGATION))
      $checkAccess = $this->otherAggregations($actionAllow, $trust_values, $trustStakeholder, $aggregation, $time_pre);
    else
      exit("Fehler bei Aggregation");
      
  }

}
