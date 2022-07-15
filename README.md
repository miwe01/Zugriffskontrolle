# Zugriffskontrolle  

Das Abschlussprojekt enthält einen Prototyp einer Zugriffskontrolle in einem sozialen Netzwerk.

Die Zugriffskontrolle ist größenteils nach dem [Rathore-Modell](https://link.springer.com/article/10.1007/s13278-017-0425-6) umgesetzt worden.

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

### Docker installieren
[Docker Installation Seite](https://docs.docker.com/get-docker/)

### Installation Redis Stack mit Docker
docker run -d --name redis-stack-server -p 6379:6379 redis/redis-stack-server:latest

[Redis Installation Seite](https://redis.io/docs/stack/get-started/install/docker/)

### Optional: Redis Insight installieren
[Redis Insight Seite](https://redis.com/redis-enterprise/redis-insight/)
```
## Github Repository
Außerdem wurde die library [redislabs-redisgraph](https://github.com/mkorkmaz/redislabs-redisgraph-php) benutzt, um den Graphen zu erstellen und Knoten/Kanten hinzuzufügen.

### Issues
Bei der Entwicklung hatte ich zwei Issues festgestellt, die ich auch gemeldet habe.
[Issue1](https://github.com/mkorkmaz/redislabs-redisgraph-php/issues/5)
[Issue2](https://github.com/mkorkmaz/redislabs-redisgraph-php/issues/6)

Falls das Repository noch nicht geupdated wurde, muss man nur in die "Pfad/Edge.php" und "Pfad/Constructor.php", den Code hinufügen

"Code1"

"Code2"

### Probleme
Wenn man den ganzen Graph löschen muss, muss man manuell in Docker die cli aufrufen und den Befehl
GRAPH.DELETE SocialNetwork


## Routen
schaue nochmal Projekt 
 /demo -> Demo ausprobieren
 /add -> Knoten/Kanten hinzufügen
 /create -> Massendaten erstellen

## Wichtige Dateien
### Controller
Controller -> Verarbeitet Anfrage ob Benutzer Zugriff auf Resource hat und gibt Endresultat zurück 
ApiController -> schickt Api Anfragen weiter an Model
FactoryController -> erstellt Massendaten
BaseController -> erstellt kleinen Graph (der gleiche wie in der Demo)

### Model
Social Network -> enthält alle Anfragen und stellt Verbindung mit Redis Datenbank her.

## Handbuch
### Wichtige Begriffe
**Stakeholder:**Benutzer der an dem Dokument beteiligt ist.
**Owner:** Eine Datei hat immer genau __einen__ Eigentümer, der auch die Aggregation bestimmt
**Coowner:** Datei kann, aber beliebig viele coowner besitzen
**Aggregation:** Bestimmt je nach Typ wie geschützt die Datei sein soll. Siehe Abschnitt xxx
**Stakeholderaktion:** Ein Owner/coowner bestimmt welche Aktionen auf einem Dokument erlaubt sind.
**Stakeholder-Vertrauen:** Beschreibt zwischen 0 und 1, wie stark die Bindung ist
**Pfad-Vertrauen:** Wird berechnet bei der __Traversierung__


## Graph Umsetzung
Der Graph enthält zwei Arten von Knoten, Benutzer und Dokumente und zwei Arten von Kanten Benutzer-Benutzer, Benutzer->Dokument.

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

### Aggregationstyp
**avg**  = Durschnitt von Stakeholder Trust auf Resource muss höher sein als Pfad Traversierung von den jeweiligen Pfaden
**conj** = Alle Pfad-Vertrauen müssen höher sein, als das Vertrauen von den Stakeholdern
**disj** = Mindestens ein Pfad-Vertrauen muss höher sein
**maj**  = Mehr als die Hälfte der Pfad-Vertrauen muss höher sein als die von den Stakeholdern

## Traversierung
Man traversiert immer vom Stakeholder zu dem Benutzer der angefragt hat. Man rechnet den schnellsten Weg aus.

## Eigenschaften
Das Projekt enthält folgende Funktionen:

- Graph Traversierung mit Start und Endknoten, dabei wird das Gewicht von jeder Kante zusammengerechnet, um so den schnellsten Pfad zu ermitteln
- Logging Parameter, der alle wichtigen Berechnungen speichert und zum Schluss ausgibt
- Multi-Party Dokumente, die ermöglichen, dass mehrere Benutzer gleichzeitig das gleiche Dokument besitzen
- Eine Demo um das System zu testen
- Knoten/Kanten können in Graph hinzugefügt werden
- 

## Beispiel 

"Bild einfügen" kleines nur 4 Knoten oder so

file1 <- Alice(owner), aggregation(disj), trust(0.7), read
      <- Bob (coowner), trust(0.6), like, read

Bedeutet: Zwei Benutzer haben jetzt Mitspracherecht an der Datei file1. 
Die Benutzer bestimmen über die Aktionen (like, read...) was erlaubt ist. Wenn der Benutzer die Aktion like versucht auf das Dokument muss jeder Stakeholder die Aktion auch besitzen, sonst darf man nur drauf lesen.
Außerdem muss das Vertrauen vom jeweiligen Stakeholder höher sein als der Pfad Vertrauen (siehe Traversierung).

Wie sicher das ganze ist von außen hängt stark mit dem Aggregationstyp ab