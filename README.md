# RRZE Pieksy

System zur Vergabe von Impfterminen. 

Fork vom RRZE-RSVP (Platzbuchungssystem der FAU). 
Entfernt werden die Funktionalitäten:
- LDAP
- Equipments
- Kontaktverfolgung
- QR / PDF

Das Plugin ermöglicht das Reservieren und Stornieren von Impfterminen.

## Download 

GitHub-Repo: https://github.com/RRZE-Webteam/rrze-vac


## Autor 
RRZE-Webteam , http://www.rrze.fau.de

## Copyright

GNU General Public License (GPL) Version 3 


## Zweck 

Mit dem Buchungssystems RRZE-Pieksy können Trancen festgelegt werden, zu denen sich Personen nach IdM-Anmeldung registieren, ein- und ausbuchen können. 
Trancen: gruppenweise durchgeführte Impfungen mit fester Anzahl an Personen in einem definierten Zeitfenster.

## Dokumentation

Eine vollständige Dokumentation mit vielen Anwendungsbeispielen findet sich auf der Seite: 
https://www.wordpress.rrze.fau.de/plugins/fau-und-rrze-plugins/pieksy/


## Verwendung der SSO-Option

Das Plugin nutzt die Anmeldung für zentral-vergebene Kennungen von Studierenden und Beschäftigten der Universität Erlangen-Nürnberg. Damit ist der Zugriff auf die Reservierungsseite nur für Personen autorisiert, die eine IdM-Kennung haben.

Damit die SSO-Option funktioniert, muss zuerst das FAU-WebSSO-Plugin installiert und aktiviert werden.
Vgl. https://github.com/RRZE-Webteam/fau-websso

Folgen Sie dann den Anweisungen unter folgendem Link:
https://github.com/RRZE-Webteam/fau-websso/blob/master/README.md

Nachdem Sie den korrekten Betrieb des FAU-WebSSO-Plugins überprüft haben, können Sie die SSO-Option des RSVP-Plugins verwenden.


## Speicherung der Daten

Alle persönlichen Daten werden verschlüsselt gespeichert und automatisch nach 4 Wochen gelöscht. Für die Suche werden sie entschlüsselt und auf der Website bereitgestellt.
