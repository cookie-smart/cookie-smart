# Registro delle modifiche

Novità del plugin WordPress di Cookie Smart. Le versioni sono scaricabili dalle [release](../../../releases).

## 1.3.0

- Guida all'attivazione per chi installa il plugin senza un account: come creare l'account, aggiungere il sito e trovare la chiave, con i link al sito di Cookie Smart.
- Avviso nella pagina Plugin e nella Bacheca finché il plugin non è collegato.

## 1.2.0

- Aggiornamenti dalla bacheca di WordPress: le nuove versioni compaiono tra gli aggiornamenti dei plugin, anche con gli aggiornamenti automatici. Lo zip viene scaricato dalle release GitHub di Cookie Smart e verificato con l'impronta sha256 prima dell'installazione.

## 1.1.0

- Il banner si carica dalla CDN europea di Cookie Smart quando il server la indica: più veloce e disponibile anche se il server principale non risponde.
- Se il server non risponde, il plugin continua a usare l'ultima configurazione ricevuta: gli script restano bloccati.

## 1.0.0

- Prima versione pubblica.
- Banner Cookie Smart inserito come primo elemento del `<head>`.
- Blocco lato server degli script e degli iframe dei servizi da bloccare (anche con i plugin di cache).
- Servizi da bloccare letti dal server Cookie Smart: un'unica fonte per banner e plugin.
- Ponte verso la WP Consent API.
- Menu dedicato con icona e shortcode [cookie_smart_preferences] per il link «Preferenze cookie».
- Server predefinito https://cookiesmart.it: basta inserire la chiave del sito.
