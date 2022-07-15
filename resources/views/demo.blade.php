<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>Demo</title>
        <script src="https://cdn.jsdelivr.net/npm/axios/dist/axios.min.js"></script>
        <style>
            h1{
                border-bottom: 1px solid black;
            }
        </style>
    </head>
    <body>
        <h1>Demonstration der Zugriffskontrolle</h1>
        <p><"Bild"></p>

        <select id="users">
        </select>
        <select id="resources"></select>
        
        <select id="actions">
            <option value="comment">Schreiben</option>
            <option value="like">Liken</option>
        </select>
        <input type="checkbox" id="logging">Logging    
        <button onclick="pressed()">Drücken</button>

        <div id="output"></div>
    </body>


    <script>
        function getData($url, $resource){
            axios.get($url)
            .then(function (response) {
                if(response.data.length != 0){
                    var select = document.getElementById("users");
                    var option = document.createElement("option");
                    option.text="Benutzer auswählen";
                    option.value = "";

                    if($resource){
                        select = document.getElementById("resources");
                        option.text="Resource auswählen";
                    }

                    select.appendChild(option);

                    for(let i=0;i<response.data.length;i++){
                        option = document.createElement("option");
                        option.text = response.data[i][0] + " " + response.data[i][1]
                        option.value = response.data[i][0] + " " + response.data[i][1]

                        if($resource){
                            option.text = response.data[i];
                            option.value = response.data[i];
                        }
                        
                        select.appendChild(option);
                    }
                }
            })
        }

        function pressed(){
            document.getElementById("output").innerHTML = "";

            let u = document.getElementById("users").value;
            
            let a = u.split(" ")
            let s = ""

            let r = document.getElementById("resources").value;
            
            let action = document.getElementById("actions").value;
            let logging = document.getElementById("logging").checked

            
            if(u == "" || r == ""){
                document.getElementById("output").innerHTML = "User oder Resource nicht ausgewählt";
                return;
            }
            if(logging){
                s = '&logAllow=true'
            }

            

            axios.get('/access?firstname=' + a[0] + '&lastname=' + a[1] + '&file=' + r + '&action=' + action + s)
            //const axios = require('axios').default;
            // axios.get('/test', {
            //     firstname= "fna",
            //     lastname = "anc",
            //     file     = "file 1",
            //     action   = "Read"
            // })            
            .then(function (response) {
                for(let i=0;i<(response.data).length;i++){
                    document.getElementById("output").innerHTML += response.data[i]
                }
                
            })
            .catch(function (error) {
                console.log(error);
            });
        }

        getData("/api/allUsers", false)
        getData("/api/allResources", true)
        </script>
</html>
