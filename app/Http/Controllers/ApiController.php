<?php

namespace App\Http\Controllers;
use App\Models\SocialNetwork;
use Illuminate\Http\Request;

class ApiController extends Controller
{
    // Bekomme alle Benutzer Namen
    public function allUsers_api(){
        $model = new SocialNetwork();
        return $model->allUsers();
    }

    // Bekomme alle Resourcen Namen
    public function allResources_api(){
      $model = new SocialNetwork();
      return $model->allResources();
    }
      
    // Benutzer hinzufügen
    public function addUser_api(Request $req){
      if (!$req->has('firstname') || !$req->has('lastname') || !$req->has('age'))
          return "Fehler: Parameter falsch";

      $model = new SocialNetwork();
      return $model->addUser($req->input('firstname'), $req->input('lastname'), $req->input('age'));
    }

    // Datei hinzufügen
    public function addFile_api(Request $req){
      if (!$req->has('name'))
          return "Fehler: Parameter falsch";
      
      $model = new SocialNetwork();
      return $model->addFile($req->input('name'));
    }

    // Kante Benutzer->Benutzer hinzufügen
    public function addEdgeUserUser_api(Request $req){
      if (!$req->has('user1') || !$req->has('user2') || !$req->has('relation') || !$req->has('trust'))
          return "Fehler: Parameter falsch";
      
      $model = new SocialNetwork();
      return $model->addEdgeUserUser($req->input('user1'), $req->input('user2'), $req->input('relation'), $req->input('trust'));
    }

    // Kante Benutzer->Datei hinzufügen
    public function addEdgeUserFile_api(Request $req){
      if (!$req->has('user') || !$req->has('file') || !$req->has('stakeholder') ||  !$req->has('trust') || !$req->has('actions'))
          return "Fehler: Parameter falsch";

      $model = new SocialNetwork();
      return $model->addEdgeUserFile($req->input('user'), $req->input('file'), $req->input('stakeholder'), $req->input('trust'), $req->input('actions'));
    }
}
