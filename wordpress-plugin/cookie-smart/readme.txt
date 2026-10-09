=== Cookie Smart ===
Requires at least: 6.0
Requires PHP: 7.4
Stable tag: 1.3.0
License: GPLv2 or later

Banner cookie e blocco preventivo degli script, collegato al server Cookie Smart.

== Installazione ==
Serve un account Cookie Smart: crealo su https://cookiesmart.it/app/register (14 giorni di prova gratuita, senza carta di credito) e aggiungi il tuo sito nel pannello.

1. Carica la cartella cookie-smart in wp-content/plugins (o lo zip da Plugin > Aggiungi nuovo > Carica).
2. Attiva il plugin.
3. Menu laterale > Cookie Smart (icona dello scudo): incolla la chiave del sito, che trovi nel pannello Cookie Smart nella pagina del sito.
4. Escludi lo script di Cookie Smart da minificazione, combinazione e "delay JS" del plugin di cache.

== Changelog ==

= 1.3.0 =
* Guida all'attivazione per chi installa il plugin senza un account: come creare l'account, aggiungere il sito e trovare la chiave, con i link al sito di Cookie Smart.
* Avviso nella pagina Plugin e nella Bacheca finché il plugin non è collegato.

= 1.2.0 =
* Aggiornamenti dalla bacheca di WordPress: le nuove versioni compaiono tra gli aggiornamenti dei plugin, anche con gli aggiornamenti automatici. Lo zip viene scaricato dalle release GitHub di Cookie Smart e verificato con l'impronta sha256 prima dell'installazione.

= 1.1.0 =
* Il banner si carica dalla CDN europea di Cookie Smart quando il server la indica: più veloce e disponibile anche se il server principale non risponde.
* Se il server non risponde, il plugin continua a usare l'ultima configurazione ricevuta: gli script restano bloccati.

= 1.0.0 =
* Prima versione pubblica.
* Banner Cookie Smart inserito come primo elemento del `<head>`.
* Blocco lato server degli script e degli iframe dei servizi da bloccare (anche con i plugin di cache).
* Servizi da bloccare letti dal server Cookie Smart: un'unica fonte per banner e plugin.
* Ponte verso la WP Consent API.
* Menu dedicato con icona e shortcode [cookie_smart_preferences] per il link «Preferenze cookie».
* Server predefinito https://cookiesmart.it: basta inserire la chiave del sito.
