<?php

namespace App\Http\Controllers;
use Predis;
use Redislabs\Module\RedisGraph\RedisGraph;

use App\Models\SocialNetwork;
use Redislabs\Module\RedisGraph\Node;
use Redislabs\Module\RedisGraph\Edge;

use Redislabs\Module\RedisGraph\GraphConstructor;
use Redislabs\Module\RedisGraph\Query;
use Faker;

//use mkorkmaz\src\RedisGraph;





class FactoryController extends Controller
{
    private $faker;

    public function create(){
        $redisClient = new Predis\Client();
        $redisGraph = RedisGraph::createWithPredis($redisClient);

        $redisGraph->delete("SocialNetwork");


        $model = new SocialNetwork();
        $conn = $model->getConnection();

        $this->faker = Faker\Factory::create();


        $graph = $model->createGraph("SocialNetwork");

        $this->createPerson($graph, $conn, 50, 2);
        
        // 10000 Nodes mat 100% Edges = 10000
        //$this->createPerson($graph, $conn, 30000, 1); 
        //$this->createPerson($graph, $conn, 20000, 2); 
        //$this->createPerson($graph, $conn, 10000, 1); 



        //$this->createPersons($graph, $conn, 20000, true);
        //$this->createPersons($graph, $conn, 20000, false);
        return "OK";
    }

    public function getAttributes(){
        $property = [
            'firstname' => $this->faker->firstName(),
            'lastname' => $this->faker->lastName(),
            'age' =>  $this->faker->numberBetween(17, 70),
            'email' => $this->faker->email(),
            'phone' => $this->faker->phoneNumber(),
            'gender' => $this->faker->randomElement(['männlich', 'weiblich', 'divers']),
            'degree' => $this->faker->randomElement(["Informatik", "Wirtschaftsinformatik", "MCD", "Elektrotechnik"]) // ...
        ];

        return $property;
    }

    public function getEdgeAttributes(){
        $edgeProperties = [
            'name' => $this->faker->randomElement(['friends', 'family', 'coworker', 'others']),
            'distance'=> $this->faker->numberBetween(1,9) / 10
        ];

        return $edgeProperties;
    }

    public function Stakeholder($owner){
        $edgeProperties = [
            'trust' => $this->faker->numberBetween(1,9) / 10,
            'action'=> $this->faker->randomElement(['like', 'comment', 'write', '']),
            'type' => $this->faker->randomElement(['disj', 'maj', 'conj', 'avg'])
        ];
        if($owner)
            $edgeProperties["stakeholder"] = "owner";
        else
            $edgeProperties["stakeholder"] = "coowner";

        return $edgeProperties;
    }



    public function createPerson($graph, $conn, $n, $percentEdges){
        $time_pre = microtime(true);
        $labelSource =  'person';
        $labelSource2 =  'file';

        $a = [];

        for($i=0;$i<$n;$i++){
            $name1 = $this->getAttributes();

            $person1 = Node::createWithLabelAndProperties($labelSource, $name1);

            $a[] = $person1;

            $graph->addNode($person1);
        }
        
        
        for($i=0;$i<($n/2);$i++){
            $property = [
                'name' => "file " . $i,
            ];

            $stakeholder1 = $this->Stakeholder(true);

            $file = Node::createWithLabelAndProperties($labelSource2, $property);
            $edge1 = Edge::create($person1, 'have', $file)->withProperties($stakeholder1);

            $graph->addNode($file);
            $graph->addEdge($edge1);


            $stakeholder2 = $this->Stakeholder(false);
            $stakeholder3 = $this->Stakeholder(false);
            
            $rng = $this->faker->numberBetween(0, count($a)-1);
            $rng2 = $this->faker->numberBetween(0, count($a)-1);

            $edge2 = Edge::create($a[$rng], 'have', $file)->withProperties($stakeholder2);
            $edge3 = Edge::create($a[$rng2], 'have', $file)->withProperties($stakeholder3);

            $graph->addEdge($edge2);
            $graph->addEdge($edge3);        
        }


        $total = ($n/2)*3;

        $total2 = $n + ($n/2);

        for($i=0;$i<($n*$percentEdges);$i++){
            $rng = $this->faker->numberBetween(0, count($a)-1);
            $rng2 = $this->faker->numberBetween(0, count($a)-1);

            $edge = Edge::create($a[$rng], 'is', $a[$rng2])->withProperties($this->getEdgeAttributes());
            $graph->addEdge($edge);
        }

        $total += $i;

        $commitQuery = $graph->getCommitQuery();
        $result = $conn->commit($commitQuery);

        $time_post = microtime(true);
        $s = ($time_post - $time_pre);

        echo "Summe der Kanten: " . $total . "<br>";
        echo "Summe der Knoten: " . $total2 . "<br>";
        echo "Duration Loop: " . $s . "s<br>";
    }



}
