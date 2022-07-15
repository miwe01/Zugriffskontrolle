<?php

namespace App\Http\Controllers;

use Exception;
use Illuminate\Http\Request;

use Predis;
//use mkorkmaz\src\RedisGraph;

use Redislabs\Module\RedisGraph\RedisGraph;
use Redislabs\Module\RedisGraph\Node;
use Redislabs\Module\RedisGraph\Edge;
use Redislabs\Module\RedisGraph\GraphConstructor;
use Redislabs\Module\RedisGraph\Query;


use MP\Cypher\QueryBuilder;

class BaseController extends Controller
{

    function test(){
        $redisClient = new Predis\Client();
        $redisGraph = RedisGraph::createWithPredis($redisClient);

        $result = $redisGraph->delete("SocialNetwork");


        // *****************Network****************************

        $labelSource =  'person';
        $labelDestination =  'file';

        $propertiesSource = ['firstname' => 'Alice', 'lastname' => 'Muller', 'age'=>27];
        $propertiesDestination = ['name' => 'doc1'];
        $edgeProperties = ['stakeholder' => 'owner', 'trust' => '0.8', 'action'=>"comment", 'type'=> 'disj'];
        

        $person1 = Node::createWithLabel($labelSource)->withProperties($propertiesSource);

        $propertiesSource = ['firstname' => 'Bob', 'lastname' => 'Adams', 'age'=>20];

        $person2 = Node::createWithLabel($labelSource)->withProperties($propertiesSource);

        $document = Node::createWithLabelAndProperties($labelDestination, $propertiesDestination);

        $edge1 = Edge::create($person1, 'have', $document)->withProperties($edgeProperties);


        $edgeProperties = ['stakeholder' => 'coowner', 'trust' => '0.7', 'action'=>"like"];
        
        $edge12 = Edge::create($person2, 'have', $document)->withProperties($edgeProperties);
        $edge13 = Edge::create($person2, 'have', $document)->withProperties($edgeProperties);

        $edgeProperties = ['stakeholder' => 'coowner', 'trust' => '0.7', 'action'=>"comment"];
        $edge14 = Edge::create($person2, 'have', $document)->withProperties($edgeProperties);


        
        $edgeProperties = ['name' => 'friends', 'distance'=>0.6];
        $edgeProperties2 = ['name' => 'family', 'distance'=>0.9];
        $edgeProperties3 = ['name' => 'family', 'distance'=>0.7];

        

        $edge2 = Edge::create($person2, 'is', $person1)->withProperties($edgeProperties);
        $edge3 = Edge::create($person1, 'is', $person2)->withProperties($edgeProperties2);

        // charly
        $propertiesSource = ['firstname' => 'Charly', 'lastname' => 'Wagner', 'age'=>23];

        $person3 = Node::createWithLabel($labelSource)->withProperties($propertiesSource);

        $edge4 = Edge::create($person3, 'is', $person2)->withProperties($edgeProperties);
        $edge5 = Edge::create($person2, 'is', $person3)->withProperties($edgeProperties2);


        // david
        $propertiesSource = ['firstname' => 'David','lastname' => 'Freitas','age'=>20];

        $person4 = Node::createWithLabel($labelSource)->withProperties($propertiesSource);

        $edge6 = Edge::create($person4, 'is', $person1)->withProperties($edgeProperties);
        $edge7 = Edge::create($person1, 'is', $person4)->withProperties($edgeProperties2);

        // emil
        $propertiesSource = ['firstname' => 'Emil','lastname' => 'Hansen', 'age'=>25];

        $person5 = Node::createWithLabel($labelSource)->withProperties($propertiesSource);

        $edge8 = Edge::create($person4, 'is', $person5)->withProperties($edgeProperties);
        $edge9 = Edge::create($person5, 'is', $person4)->withProperties($edgeProperties2);



        $edge10 = Edge::create($person4, 'is', $person3)->withProperties($edgeProperties3);
        $edge11 = Edge::create($person3, 'is', $person4)->withProperties($edgeProperties3);

        $graph = new GraphConstructor('SocialNetwork');

 
        $graph->addNode($person1);
        $graph->addNode($person2);
        $graph->addNode($person3);
        $graph->addNode($person4);
        $graph->addNode($person5);

        $graph->addNode($document);
        $graph->addEdge($edge1);
        $graph->addEdge($edge2);
        $graph->addEdge($edge3);
        $graph->addEdge($edge4);
        $graph->addEdge($edge5);
        $graph->addEdge($edge6);
        $graph->addEdge($edge7);
        $graph->addEdge($edge8);
        $graph->addEdge($edge9);
        $graph->addEdge($edge10);
        $graph->addEdge($edge11);
        $graph->addEdge($edge12);
        $graph->addEdge($edge13);
        $graph->addEdge($edge14);
        

        $commitQuery = $graph->getCommitQuery();



        
    


        // $commitQuery = 'GRAPH.DELETE SocialNetwork';
        $result = $redisGraph->commit($commitQuery);
        //$result->delete("SocialNetwork");


        
        //********************************************//


        // $matchQueryString = 'MATCH (p:person)-[v:have]-(p2:document) RETURN p.name, v.name, p2.name';

        $matchQueryString = 'MATCH (p:person {name: "Alice"})-[v:is]->(p2:person {name:"Bob"}) RETURN p.name, v.name, p2.name';

        $matchQueryString = "MATCH p=(from:person{name:'Alice'}), (to:person{name:'Charly'}) WITH from, to MATCH path = (from)-[:is*1..5]->(to) WITH REDUCE (total = 0, r in relationships(path) | total + r.distance) as cost, path ORDER BY cost RETURN [node IN nodes(path) | node.name], cost LIMIT 1";


        // RESET
        //$matchQueryString = 'GRAPH.DELETE NETWORK';

        $matchQuery = new Query('SocialNetwork', $matchQueryString);
        $result = $redisGraph->query($matchQuery);
        $resultSet = $result->getResultSet();

        echo "Graph wurde erstellt";  
    }
    
}
