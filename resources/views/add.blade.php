<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>Add Node/Edge</title>
        <script src="https://cdn.jsdelivr.net/npm/axios/dist/axios.min.js"></script>
        <style>
            h1{
                border-bottom: 1px solid black;
            }
        </style>
    </head>
    <body>
        <h1>Knoten oder Kante hinzufügen</h1>
        
        <p>Dokument</p>
        <input type="text" id="name" placeholder="Name">
        <button onclick="pressFile()">Drücken</button>
        <div id="output1"></div>

        <p>Person</p>
        <input type="text" id="firstname" placeholder="Vorname">
        <input type="text" id="lastname" placeholder="Nachname">
        <input type="text" id="age" placeholder="Alter">
        <button onclick="pressUser()">Drücken</button>
        <div id="output2"></div>

        <p>Kante Person zu Person</p>
        <select id="users1"></select>
        <select id="relationships">
            <option value="friends">Freunde</option>
            <option value="family">Familie</option>
            <option value="others">Anders</option>
        </select>
        <input type="text" id="trust1" placeholder="Vertrauen(optional)">
        <select id="users2"></select>
        <button onclick="pressUserUser()">Drücken</button>
        <div id="output3"></div>

        <p>Kante Person zu Dokument</p>
        <select id="users3"></select>
        <select id="resources"></select>
        <select id="stakeholder">
            <option value="owner">Besitzer</option>
            <option value="coowner">Mitbesitzer</option>
        </select>
        <input type="text" id="trust2" placeholder="Vertrauen">
        <input type="checkbox" id="read" value="read">Lesen
        <input type="checkbox" id="like" value="like">Like
        <input type="checkbox" id="comment" value="comment">Kommentieren
        <button onclick="pressUserFile()">Drücken</button>
        <div id="output4"></div>

    </body>


    <script>
        function getDataUsers(){
            const url = "/api/allUsers";
            const id = "users";
            const length = 3;

            axios.get(url)
            .then(function (response) {
                if(response.data.length != 0){
                    for(let i=1;i<=length;i++){
                        var select = document.getElementById(id + i);

                        for(let i=0;i<response.data.length;i++){
                            let option = document.createElement("option");
                            option.text = response.data[i][0] + " " + response.data[i][1]
                            option.value = response.data[i][0] + " " + response.data[i][1]
                            
                            console.log(option.text)
                            select.appendChild(option);
                        }
                    }
                    
                }
            })
        }

        function getData($url, $id){
            axios.get($url)
            .then(function (response) {
                if(response.data.length != 0){
                    console.log(response.data)
                    var select = document.getElementById($id);

                    for(let i=0;i<response.data.length;i++){
                        option = document.createElement("option");
                        option.text = response.data[i];
                        option.value = response.data[i];
                        select.appendChild(option);
                    }
                        
                    }
            })
        }

        getDataUsers()
        getData("/api/allResources", "resources")
        //getData("/api/allRelationships", "relationships")
        

        function pressFile(){
            let name = document.getElementById("name").value;
            
            if(name == ""){
                document.getElementById("output").innerHTML = "Kein Name der Datei gegeben";
                return;
            }

            axios.get('/api/addFile?name=' + name)
            .then(function (response) {
                document.getElementById("output1").innerHTML = response.data
            })
            .catch(function (error) {
                document.getElementById("output1").innerHTML = error.data
            });
        }

        function pressUser(){
            let firstname = document.getElementById("firstname").value;
            let lastname = document.getElementById("lastname").value;
            let age = document.getElementById("age").value;
            
            if(firstname == "" || lastname == "" || age == ""){
                document.getElementById("output").innerHTML = "Nicht alle Felder ausgefüllt";
                return;
            }

            axios.get('/api/addUser?firstname=' + firstname + '&lastname=' + lastname + '&age=' + age)
            .then(function (response) {
                document.getElementById("output2").innerHTML = response.data
            })
            .catch(function (error) {
                document.getElementById("output2").innerHTML = error.data
            });
        }
        
        function pressUserUser(){
            let u1 = document.getElementById("users1").value;
            let u2 = document.getElementById("users2").value;
            let relation = document.getElementById("relationships").value;
            let trust = document.getElementById("trust1").value;

            axios.get('/api/addEdgeUserUser?user1=' + u1 + '&user2=' + u2 + '&relation=' + relation + '&trust=' + trust)        
            .then(function (response) {
                document.getElementById("output3").innerHTML = response.data
            })
            .catch(function (error) {
                document.getElementById("output3").innerHTML = error.data
            });
        }

        function pressUserFile(){
            let trust = document.getElementById("trust2").value;
            let checkboxes = document.querySelectorAll('input[type="checkbox"]:checked');
            if(trust == "" || checkboxes.length == 0){
                document.getElementById("output4").innerHTML = "Nicht alle Felder ausgefüllt";
                return;
            }


            document.getElementById("output4").innerHTML = "";

            let u = document.getElementById("users3").value;
            let f = document.getElementById("resources").value;
            let stakeholder = document.getElementById("stakeholder").value;
            

            
            let a = [];
            for (var i = 0; i < checkboxes.length; i++) {
                 a.push(checkboxes[i].value)
            }


            axios.get('/api/addEdgeUserFile?user=' + u + '&file=' + f + '&stakeholder=' + stakeholder + '&trust=' + trust + '&actions=' + a)        
            .then(function (response) {
                document.getElementById("output4").innerHTML = response.data
            })
            .catch(function (error) {
                document.getElementById("output").innerHTML = error.data
            });
        }

        </script>
</html>
