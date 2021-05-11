# RRZE Vac

System zur Vergabe von Impfterminen. 

Fork vom RRZE-RSVP (Platzbuchungssystem der FAU)
Das Plugin ermöglicht das Reservieren, Einbuchen und Ausbuchen von Impfterminen.

## Download 

GitHub-Repo: https://github.com/RRZE-Webteam/rrze-vac


## Autor 
RRZE-Webteam , http://www.rrze.fau.de

## Copyright

GNU General Public License (GPL) Version 3 


## Zweck 

Mit dem Buchungssystems RRZE-Vac können Impf-Trancen festgelegt werden, zu denen sich Personen nach IdM-Anmeldung registieren, ein- und ausbuchen können.
Der Name leitet sich von der Abkürzung "vac" aus dem lat. "vaccination" für "Impfung" ab.

## Dokumentation

Eine vollständige Dokumentation mit vielen Anwendungsbeispielen findet sich auf der Seite: 
https://www.wordpress.rrze.fau.de/plugins/fau-und-rrze-plugins/vac/


## Verwendung der SSO-Option

Das Plugin nutzt die Anmeldung für zentral-vergebene Kennungen von Studierenden und Beschäftigten der Universität Erlangen-Nürnberg. Damit ist der Zugriff auf die Reservierungsseite nur für Personen autorisiert, die eine IdM-Kennung haben.

Damit die SSO-Option funktioniert, muss zuerst das FAU-WebSSO-Plugin installiert und aktiviert werden.
Vgl. https://github.com/RRZE-Webteam/fau-websso

Folgen Sie dann den Anweisungen unter folgendem Link:
https://github.com/RRZE-Webteam/fau-websso/blob/master/README.md

Nachdem Sie den korrekten Betrieb des FAU-WebSSO-Plugins überprüft haben, können Sie die SSO-Option des RSVP-Plugins verwenden.


## Speicherung der Daten

Alle persönlichen Daten werden verschlüsselt gespeichert und automatisch nach 4 Wochen gelöscht. Für die Suche werden sie entschlüsselt und auf der Website bereitgestellt.
