# Zugriffskontrolle  

Das Abschlussprojekt enthält einen Prototyp einer Zugriffskontrolle für ein soziales Netzwerk.

Die Zugriffskontrolle ist größenteils nach dem [Rathore-Modell](https://link.springer.com/article/10.1007/s13278-017-0425-6) umgesetzt worden.

## Wichtige Eigenschaften
Das Projekt enthält folgende Funktionen:

- Graph Traversierung mit Start und Endknoten, dabei wird das Gewicht von jeder Kante zusammengerechnet, um so den schnellsten Pfad zu ermitteln
- Logging Parameter, der alle wichtigen Berechnungen speichert und zum Schluss ausgibt
- Multi-Party Dokumente, die ermöglichen, dass mehrere Benutzer gleichzeitig das gleiche Dokument besitzen
- Eine Demo um das System zu testen
- Knoten/Kanten können in Graph hinzugefügt werden

## Installation Windows

```
### Repository clonen
git clone https://github.com/miwe01/Zugriffskontrolle Zugriffskontrolle

### Abhängigkeiten mit Composer installieren 
composer install

### Die Datei .env.example umbenennen in .env
ren .env.example .env

### Application key generieren
php artisan key:generate

### Docker Installation Seite
https://docs.docker.com/get-docker/

### Installation Redis Stack mit Docker
docker run -d --name redis-stack-server -p 6379:6379 redis/redis-stack-server:latest

### Redis Installation Seite
https://redis.io/docs/stack/get-started/install/docker/

### Optional: Redis Insight installieren
https://redis.com/redis-enterprise/redis-insight/
```

## Github Repository
Außerdem wurde die library [redislabs-redisgraph](https://github.com/mkorkmaz/redislabs-redisgraph-php) benutzt, um den Graphen zu erstellen und Knoten/Kanten hinzuzufügen.

### Issues
Bei der Entwicklung hatte ich zwei Issues festgestellt, die ich auch gemeldet habe.<br/>
[Issue1](https://github.com/mkorkmaz/redislabs-redisgraph-php/issues/5)<br/>
[Issue2](https://github.com/mkorkmaz/redislabs-redisgraph-php/issues/6)<br/>

#### Letztes Update: 16.07
__Issue1__ wurde nur in Node gelöst, deshalb nochmal beide Dateien überprüfen ob ein "double" Check ist (siehe Issue1).<br/>
Methode: getPropValueWithDataType($propValue)<br/>
/vendor/mkorkmaz/redislabs-redisgraph-php/src/RedisGraph/Node.php<br/>
/vendor/mkorkmaz/redislabs-redisgraph-php/src/RedisGraph/Edge.php<br/>

__Issue2__, wurde noch nicht behoben. Code aus Issue2 nehmen.<br/>
Methode: getCommitQuery().<br/>
/vendor/mkorkmaz/redislabs-redisgraph-php/src/RedisGraph/GraphConstructor.php


## Routen
 / -> Demo ausprobieren (vorher Graph mit /graph erstellen)<br/>
 /graph -> kleinen Graph erstellen <br/>
 /add -> Knoten/Kanten hinzufügen<br/>
 /create -> Massendaten erstellen

## Wichtige Dateien
### Controller
Controller -> Verarbeitet Anfrage ob Benutzer Zugriff auf Resource hat und gibt Endresultat zurück <br/>
ApiController -> schickt Api Anfragen weiter an Model<br/>
FactoryController -> erstellt Massendaten<br/>
BaseController -> erstellt kleinen Graph (der gleiche wie in der Demo)

### Model
SocialNetwork -> enthält alle Anfragen bezüglich des Thema Zugriffskontrolle<br/>
SocialNetwork_api -> enthält Anfragen für Benutzer/Resourcen zu erstellen/zurückzugeben

## Handbuch
### Wichtige Begriffe
**Stakeholder:** Benutzer der an dem Dokument beteiligt ist.<br/>
**Owner:** Besitzer der Datei, jede Datei hat immer genau **einen** Besitzer, der auch die Aggregation bestimmt<br/>
**Coowner:** Beliebig viele Mitbesitzer, können nicht Aggregation bestimmen<br/>
**Aggregation:** Bestimmt je nach Typ wie geschützt die Datei sein soll. (Siehe Abschnitt Aggregationstypen)<br/>
**Stakeholderaktion:** Ein Owner/coowner bestimmt welche Aktionen auf einem Dokument erlaubt sind.<br/>
**Stakeholder-Vertrauen:** Beschreibt zwischen 0 und 1, wie stark die Bindung zueinander ist.<br/>
**Pfad-Vertrauen:** Wird berechnet bei der **Traversierung**


## Graph Umsetzung
Der Graph enthält zwei Arten von Knoten Benutzer & Dokumente und zwei Arten von Kanten Benutzer-Benutzer, Benutzer->Dokument.

### Benutzer
Benutzer können Beziehungen mit anderen Benutzern und/oder auch Dokumenten haben.

Die Beziehung zwischen Benutzer-Benutzer ist definiert mit dem Beziehungstyp und dem Gewicht.
(Diese Attribute können natürlich beliebig ausgetauscht oder erweitert werden)

Der Beziehungstyp ist nur eine Bezeichnung und das Gewicht bestimmt wie stark die Beziehung zwischen den beiden ist.

Benutzer-Dokumente sind bestimmt ob der Benutzer Besitzer oder Mitbesitzer ist, das Vertrauen zu der Datei und die Aktion die erlaubt sind. 

### Dokumente
Dokumente haben normalerweise immer eine Beziehung mit einem oder mehreren Benutzern (Stakeholder).

Der Owner des Dokumentes bestimmt die __Aggregation__ und außerdem welche Aktionen erlaubt sind.

Der Coowner gibt nur die erlaubten Aktionen an.

### Aggregationstypen
**avg**  = Durschnitt von Stakeholder Trust auf Resource muss höher sein als Pfad Traversierung von den jeweiligen Pfaden<br/>
**conj** = Alle Pfad-Vertrauen müssen höher sein, als das Vertrauen von den Stakeholdern<br/>
**disj** = Mindestens ein Pfad-Vertrauen muss höher sein<br/>
**maj**  = Mehr als die Hälfte der Pfad-Vertrauen muss höher sein als die von den Stakeholdern

## Traversierung
Man traversiert immer vom Stakeholder zu dem Benutzer der angefragt hat. Man rechnet den schnellsten Weg aus, auf dem Weg zum Benutzer multipliziert man die Gewichte der Kanten zusammen.

```
# Code von Pfad Traversierung, gibt Gesamtgewicht zurück und welche Knoten/Kanten durschlaufen wurde
$HOPS = x;

$q = 
'MATCH (from:person{firstname:"", lastname:""}),
(to:person{firstname:"", lastname:""})
WITH from, to MATCH path = (from)-[:is*1..' . $HOPS . ']->(to) 
WITH REDUCE (total = 1, r in relationships(path) | total * r.distance) 
as cost, path ORDER BY cost DESC RETURN cost, path LIMIT 1';

```

## Zugriff
Der Benutzer hat Zugriff wenn die Aggregation stimmt, die stimmt wiederum wenn das Vertrauen größer ist wie das Stakeholder-Vertrauen. <br/>

Die Aktion die der Benutzer ausführen möchte gelingt nur wenn jeder Stakeholder die Aktion erlaubt, sonst darf man nur drauf lesen.<br/>
