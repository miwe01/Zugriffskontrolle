<?php

namespace App\Http\Controllers;

use App\Models\SocialNetwork;
use Exception;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Foundation\Bus\DispatchesJobs;
use Illuminate\Foundation\Validation\ValidatesRequests;
use Illuminate\Routing\Controller as BaseController;
use Illuminate\Http\Request;


// Alle Aggregationswerte
// ACHTUNG: im Moment ist Reihenfolge wichtig, bei Änderung kann es zu Fehlern führen
define('AGGREGATION', array('avg', 'disj' , 'maj', 'conj'));
class Controller extends BaseController
{
    use AuthorizesRequests, DispatchesJobs, ValidatesRequests;

    // Überprüft ob User Zugriff auf Ressource hat
    // args: $action -> Aktion die Benutzer gerne ausführen möchte auf Resource
    //       false, wenn Aktion nicht jeder Stakeholder hat, sonst Name von Aktion  
    //       $check = Wenn Bedingung aus Aggregation erfüllt ist 
    //       (avg hat mehr als Hälfte)
    //       (disj, maj, conj: wenn $case1/2/3 erfüllt wurde)
    private function access($check, $action){
      // $aboveAverage || $case 1 || $case2 || $case3
      if(!$check) return "Du hast keinen Zugriff, da dein Vertrauenslevel zu niedrig ist";

      if($action != "false") return "Du darfst auf die Ressource " . $action;

      else return "Du darfst nur auf die Ressource sehen (view), weil die Aktion nicht jeder Stakeholder erlaubt";
      
    }

    // Average Fall
    // 1. Schritt: Zählt wie oft true in array policy vorkommt und wenn es die Mehrheit true ist
    // 2. Schritt: Zeit stoppen
    // 3. Schritt: Gib logging oder nur Resultat mit Zeit zurück
    // args: $policy = Array mit true | false, true = Pfad-Weg-Vertrauen > Stakeholder-Vertrauen-Resource
    //       $action = Aktion die User gerne ausführen möchte, wenn false => nicht jeder Stakeholder unterstützt Aktion
    //       $time_pre = Zeit die bis jetzt abgelaufen ist
    //       $logAllow = boolean ob geloggt werden soll
    //       $log = wichtige Werte stehen da drin
    // out:  Endresultat von Zugriffskontrolle
    private function averageAggregation($policy, $action, $time_pre, $logAllow, $log){
      if($logAllow) $log[] = 
      "<h3>Aggregation</h3>
      <p>Besitzer hat <b>avg</b> Aggregation ausgewählt</p>
      <p>avg= <b>Mehr als die Hälfte</b> der Pfad-Vertrauen größer als durchschnittlicher Stakeholder-Vertrauen</p>";

        // 1. Schritt
        //source: https://stackoverflow.com/questions/5945199/counting-occurrence-of-specific-value-in-an-array-with-php
        $occurences = count(array_filter($policy, function ($n) { return $n == "true"; }));

        $aboveAverage = $occurences > round( (count( $policy )-1) /2);
        $boolString = $aboveAverage ? "true" : "false";

        // Wenn mehr true als false in $policy
        if($logAllow) $log[] = "<p>Mehr als die Hälfte richtig? " . $boolString ."</p>";
        if($aboveAverage)
          $s = $this->access(true, $action);
        else
          $s = $this->access(false, $action);
        
        // 2. Schritt
        $time_post = microtime(true);
        $sec = number_format(($time_post - $time_pre), 2, ',', '');

        // 3. Schritt
        // Wenn logging an
        if($logAllow){ 
          $log[] = "<h3>Resultat</h3> <p>" .$s . "</p>"; 
          $log[] = "<p>Zeit: " .$sec . "</p>"; 
          return $log;
        }
        return array($s, $sec);
    }

    // disj, maj, conj Fall
    // 1. Schritt: Überprüfe ob eine der cases stimmt, schaut zuerst ob richtige Aggregation und dann wenn Bedingung stimmt
    // 2. Schritt: Zeit stoppen
    // 3. Schritt: Gib logging oder nur Resultat mit Zeit zurück
    // args: $policy = Array mit true | false, true = Pfad-Vertrauen > Stakeholder Vertrauen zu Resource
    //       $action = Aktion die User gerne ausführen möchte, wenn false => nicht jeder Stakeholder unterstützt Aktion
    //       $aggregation = Aggregation von Stakeholder der angefragten Resource
    //       $time_pre = Zeit die bis jetzt abgelaufen ist
    //       $logAllow = boolean ob geloggt werden soll
    //       $log = wichtige Werte stehen da drin
    // out:  Endresultat von Zugriffskontrolle
    private function otherAggregations($policy, $action, $aggregation, $time_pre, $logAllow, $log){
      if($logAllow) $log[] = 
      "<h3>Aggregation</h3> <p>Besitzer hat <b>" . $aggregation ."</b> Aggregation ausgewählt</p><p>
      conj = <b>Alle</b> Pfad-Vertrauen größer als Stakeholder-Vertrauen<br>
      disj = <b>Mindestens</b> ein Pfad-Vertrauen größer als Stakeholder-Vertrauen<br>
      maj = <b>Mehr als die Hälfte</b> der Pfad-Vertrauen größer als Stakeholder-Vertrauen</p>";
      
      $conj = $aggregation == "conj" && !in_array("false", $policy); // alle Bedingungen müssen erfüllt sein
      
      $disj = $aggregation == "disj" && in_array("true", $policy); // mindestens eine muss erfüllt sein 
      
      $maj = $aggregation == "maj" && // über die Hälfte muss erfüllt sein
      count( array_filter( $policy, function ($n) { return $n == "true"; } )) > round( (count( $policy )-1) /2); 

      
      // 1. Schritt
      if($conj || $disj || $maj)
          $s = $this->access(true, $action);
      else
          $s = $this->access(false, $action);

      // 2. Schritt
      $time_post = microtime(true);
      $sec = number_format(($time_post - $time_pre), 2, ',', '');
      
      // 3. Schritt
      if($logAllow){ 
          $log[] = "<h3>Resultat</h3><p>" .$s . "</p>"; 
          $log[] = "<p>Zeit: " .$sec . "s</p>"; 
          return $log;
      }

      return $s;
    }


  
    // Hauptfunktion: Überprüft ob Zugriff auf Ressource und ausgewählte Aktion möglich ist
    // 1. Schritt: Überprüfe ob Parameter stimmen
    // 2. Schritt: Aggregation der Resource (vom Stakeholder), kann auch zurückgeben, dass User Stakeholder ist
    // 3. Schritt: Bekomme Pfad Vertrauen von jedem Stakeholder zurück zu Benutzer
    // 4. Schritt: Bekomme Array 
    // [0] = Aktion die User gerne ausführen möchte, wenn false => nicht jeder Stakeholder unterstützt Aktion
    // [1] = Array Policy, wenn Pfad Vertrauen > Stakeholder Vertrauen zu Resource => true, sonst false für jeden Stakeholder

    public function evaluatePolicy(Request $req){
    // 1. Schritt
    if (!$req->has('firstname') || !$req->has('lastname') || !$req->has('file') || !$req->has('action')){
      return "Fehler: Parameter falsch";
    }
    // Zeit starten
    $time_pre = microtime(true);
    $log = [];

    // Name zusammensetzen aus first und lastname
    $name = array($req->input('firstname'), $req->input('lastname'));
    $r = $req->input('file');
    $action = $req->input('action');
    
    // Wenn nicht explizit true dann immer logging aus
    $logAllow = $req->input('logAllow') ?? false;

    if($logAllow) $log[] = "<h2>Output</h2><li>User<b> " . $name[0] . " " . $name[1] . "</b> möchte auf Resource<b> " . $r . "</b> mit Aktion <b>" . $action . "</b> zugreifen</li>";

    $model = new SocialNetwork();
    try{
      // 2. Schritt
      $aggregation = ($model->StakeholderOrAggregation($r, $name));

      // Falls Benutzer Stakeholder ist
      if($aggregation === true){
        if($logAllow){
          $time_post = microtime(true);
          $sec = number_format(($time_post - $time_pre), 2, ',', '');
          
          $log[] = "<p>Du bist Stakeholder</p>";
          $log[] = "<p>Zeit: " .$sec . "s</p>";

          return $log;
        } 
        return "Du bist Stakeholder";
      }
      // 3. Schritt
      $pathValues = $model->path($r, $name, $logAllow, $log);

      // 4. Schritt
      $res = $model->stakeholderActions($r, $aggregation, $pathValues, $action, $log, $logAllow);
    } 
    catch(Exception $e){
      if($logAllow){ 
        $s = strval($e->getMessage());
        $log[] = $s;
         return $log;
      }
      return $e->getMessage();
    }
    
    // 4. Schritt
    // Array [0] Aktion die User gerne ausführen möchte, wenn false => nicht jeder Stakeholder unterstützt Aktion
    //       [1] Array Policy, wenn Pfad Vertrauen > Stakeholder Vertrauen zu Resource => true, sonst false für jeden Stakeholder
    $action = $res[0];
    $policy = $res[1];


    // Aggreggation ist average
    if($aggregation == AGGREGATION[0])
      $logging = $this->averageAggregation($policy, $action, $time_pre, $logAllow, $log);
    
    // Andere Aggregationen disj, avg, conj
    else if(in_array($aggregation, AGGREGATION))
      $logging = $this->otherAggregations($policy, $action, $aggregation, $time_pre, $logAllow, $log);
    else
      exit("Fehler bei Aggregation");
    
    return $logging;
  }

}
