# Registro delle modifiche

Novità del modulo PrestaShop di Cookie Smart. Le versioni sono scaricabili dalle [release](../../../releases).

## 1.3.0

- Guida all'attivazione per chi installa il modulo senza un account: come creare l'account, aggiungere il negozio e trovare la chiave, con i link al sito di Cookie Smart.
- Avviso nella dashboard del back office finché il modulo non è collegato.
- Basta la chiave del sito: tolto il campo «Indirizzo del server», che è sempre https://cookiesmart.it.

## 1.2.0

- Aggiornamenti dal back office: quando esce una nuova versione compare un avviso nella dashboard e nella configurazione del modulo, con il pulsante «Aggiorna».
- Lo zip viene scaricato dalle release GitHub di Cookie Smart e, prima dell'installazione, ne vengono verificati l'impronta sha256 e il contenuto.

## 1.1.0

- Il banner si carica dalla CDN europea di Cookie Smart quando il server la indica.
- Se il server non risponde, il modulo continua a usare l'ultima configurazione ricevuta: gli script restano bloccati.

## 1.0.0

- Prima versione pubblica, per PrestaShop 1.7.1 e successivi, 8 e 9.
- Banner Cookie Smart inserito come primo elemento del `<head>`, senza modificare il tema.
- Blocco lato server degli script e degli iframe dei servizi da bloccare.
- Servizi da bloccare letti dal server Cookie Smart: un'unica fonte per banner e modulo.
