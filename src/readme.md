# AmcLab\Storyteller

Auditor e logger eventi per Laravel.

...Work in progress...

## Requisiti

- ...tutto lo stack AmcLab

## A che serve?

Questo package serve a tenere traccia, su un apposito store, di tutti gli eventi che coinvolgono le entità di database e gli utenti di applicazione, consentendo in maniera semplificata di:

- tracciare tutte le operazioni di creazione, modifica, cancellazione ed eventuale restore dei record (se si usa SoftDeletes) e di alcune operazioni legate all'autenticazione di Laravel;
- segnalare l'evento appena verificatosi anche alle altre entità correlate al Model su cui si sono verificate;
- ottenere l'elenco di tutte le modifiche effettuate da uno specifico operatore e di tutte le operazioni che hanno coinvolto uno specifico record.

## Come funziona?

Storyteller lega agli eventi intercettati il dispatch di uno o più job su un'apposita coda di messaggi, in maniera tale che vengano autonomamente eseguiti in background dall'application server.

In questo modo viene garantita l'immediatezza dell'operazione richiesta, senza dover attendere che il log venga effettivamente preso in carico e scritto.

Questa operazione viene effettuata trasmettendo al job un oggetto (event) contenente una copia dell'istanza Model coinvolta e le impostazioni di connessione dell'Environment, tramite cui viene determinata la destinazione del log sul Receiver di riferimento.

## Documentazione

### Prerequisiti

Storyteller necessita che sull'applicazione chiamante sia settata un'Identity di Environment, affinché i job asincroni possano usare la stessa connessione usata da Eloquent per eseguire le operazioni richieste.

Opzionalmente, è possibile indicare lo scope attuale, in modo tale da trasmettere anche il punto dell'applicazione in cui si è scatenato l'evento.

```php
app('environment')
->setIdentity('nome')
->setScope(new ExampleScope); // opzionale
```

I job saranno automaticamente impilati nella coda "storyteller", che può essere evasa in background con l'esecuzione del relativo processo:

```bash
php artisan queue:work --queue=storyteller
```

### Log operazioni per Model

Per abilitare il log degli eventi di Eloquent, è necessario che il Model usi il trait TellableTrait:

```php
namespace App\Models;

use AmcLab\Storyteller\Traits\TellableTrait;

class ExampleModel
{
    use TellableTrait;

    ...
```

Una volta ereditato il trait, Storyteller sarà già attivo sul Model, tracciando automaticamente le operazioni legate agli eventi ```created```, ```updated```, ```deleted```, ```restored``` e ```forceDeleted```:

```php
ExampleModel::create(['nome'=>'Mario', 'cognome'=>'Rossi']);
```

Storyteller esclude per default l'intercettazione delle modifiche ai seguenti campi:

- id
- created_at
- updated_at
- deleted_at

È possibile ignorare ulteriori campi indicandoli nella proprietà $excludeFromLog del Model:

```php
protected $excludeFromLog = [ 'campo_privato', 'altro_campo_privato' ];
```

In questo modo, eventuali cambiamenti che avvengano sui campi ```campo_privato``` e/o ```altro_campo_privato``` non saranno loggati.

#### Propagazione dell'evento ai record correlati

In alcuni casi, potrebbe essere utile notificare ad altri record l'evento che si è appena verificato. Ad esempio, l'evento di creazione di una tessera andrebbe aggiunto al log dell'utente a cui questa è associata.

Per fare questo, è necessario aggiungere la proprietà $bubbles al Model:

```php
protected $bubbles = [

    // attributo del Model relazionato ad un altro Model
    'customer_id' => [

        // nome del Model correlato da avvisare
        Customer::class,

        // elenco degli eventi da notificare al Model correlato
        ['created', 'deleted']

    ],

];
```

Così facendo, qualsiasi evento ```created``` o ```deleted``` su questo Model andrà automaticamente notificato nel log del record Customer identificato dalla colonna ```customer_id``` sul Model corrente.

### Log operazioni per User (Authenticatable)

Il log degli eventi generati dai Model che implementano l'interfaccia ```Illuminate\Contracts\Auth\Authenticatable``` avviene in maniera automatica, ma soltanto per gli eventi ```Login```, ```Logout``` e ```PasswordReset```.

### Log di altre operazioni

È possibile tracciare un evento personalizzato sul log operazioni di uno specifico Model usando il metodo happened():

```php
app('storyteller')->happened(new MyEvent($param1, $param2), ExampleModel::find(14));
```

Il metodo happened() richiede un minimo di due parametri:

- $event: può essere una stringa (es. "printed"), un array con la struttura [$nome, $dati] o un oggetto Event con proprietà pubbliche, come da convenzioni di Laravel;
- $entity: un'istanza di Model o un array con la struttura [$modelClass, $recordId].

Nel caso in cui si debba propagare lo stesso evento a più Models, è possibile passarli tutti come arguments. Ad esempio, dopo la stampa di un elenco di nominativi bisogna far sì che resti traccia, sui log di ciascun nominativo, che è stata richiesta una stampa in cui questo nominativo è comparso:

```php
$listaClientiBuilder = Customers::where(...);
stampa($listaClientiBuilder);
...
...

function stampa($listaClientiBuilder) {

    foreach($listaClientiBuilder->cursor() as $cliente) {
        ...
        ...
        // piuttosto che tenere in memoria N models, creo un array
        // contenente la loro rappresentazione loggabile
        $printed[] = [get_class($cliente), $cliente->id];
    }

    // se sono arrivato fin qui, vuol dire che la generazione
    // della stampa è andata a buon fine, quindi va scritto il log
    app('storyteller')->happened('printed', ...$printed);
}
```

***Nota: il log di altre operazioni non prevede che sia automaticamente propagato agli eventuali Model collegati!***

### Come sono memorizzati i dati?

```php
array:6 [

    // tipo di documento (vedi più avanti)
    "documentType" => "EloquentDocument"

    // evento (da cui vengono tolte alcune proprietà superflue, es. model, user, ecc...)
    "event" => array:2 [
        "name" => "Happening"
        "payload" => array:1 [
            "name" => "created"
        ]
    ]

    // oggetto Carbon che rappresenta la data in cui l'evento è stato propagato
    "datetime" => Illuminate\Support\Carbon @1531297380 {#715
        date: 2018-07-11 10:23:00.0 Europe/Rome (+02:00)
    }

    // identificativo del record a cui il log si riferisce
    "affectedEntity" => array:2 [
        "name" => "App\User"
        "key" => 7292
    ]

    // responsabilità della modifica corrente (utente e scope dell'applicazione)
    "responsibility" => array:2 [
        "userId" => null
        "scope" => array:2 [
            "name" => "AmcLab\Environment\Scopes\DefaultScope"
            "data" => []
        ]
    ]

    // elenco dei cambiamenti loggati (uno per colonna, con nome attributo, vecchio e nuovo valore)
    "changes" => array:3 [
        0 => array:2 [
            "attribute" => "name"
            "changes" => array:2 [
                "oldValue" => null
                "newValue" => "dsqM"
            ]
        ]
        1 => array:2 [
            "attribute" => "password"
            "changes" => array:2 [
                "oldValue" => null
                "newValue" => "j3cz"
            ]
        ]
        2 => array:2 [
            "attribute" => "email"
            "changes" => array:2 [
                "oldValue" => null
                "newValue" => "7fcQ"
            ]
        ]
    ]
]
```

### Tipi di documenti loggati

- ```AuthDocument```: è il log di un evento di login/logout
- ```EloquentDocument```: è il log di un evento nativo di Eloquent (es. created)
- ```HappenedDocument```: è il log di un evento personalizzato (es. "printed")
- ```RelatedChangeDocument```: è il log di un evento propagato da un altro Model

## Limitazioni e possibili workaround

L'attuale versione di Storyteller non è autonomamente in grado di:

- loggare operazioni di massa invocate mediante il QueryBuilder
- gestire l'eventuale rollback dei log nel caso di operazioni sotto transazione

### Log delle operazioni di massa

Le operazioni di update e cancellazione di massa, ossia quelle che vengono invocate mediante il QueryBuilder, [non possono essere loggate](https://laravel.com/docs/5.6/eloquent#updates) poiché, non passando da Eloquent, non lanciano un evento.

Prendendo come esempio la seguente richiesta:

```php
ExampleModel::where([['id', '<', 300]])->update(['something' => Str::random(4)]);
```

L'operazione di update in oggetto non verrebbe tracciata da Storyteller perché il metodo update() viene eseguito non su un'istanza di Model, bensì su un'istanza di QueryBuilder e per questo motivo non è possibile intercettare le singole operazioni e/o i singoli record coinvolti.

Per ovviare a questo limite esistono due workaround, di seguito illustrati.

#### Iterare singolarmente i Models coinvolti

Usando il metodo get() o - meglio ancora - il metodo cursor() del QueryBuilder, è possibile lanciare le operazioni in sequenza iterando ad uno ad uno i Model coinvolti dal costrutto where:

```php
$utenti = ExampleModel::where([['id', '<', 300]]);

foreach($utenti->cursor() as $utente) {
    $utente->update(['something' => Str::random(4)]);
}
```

*Nota: l'operazione è (ovviamente) molto meno performante dell'equivalente operazione lato QueryBuilder!*

#### Lanciare un equivalente evento personalizzato

*Nota: al momento non è ancora stato formalizzato alcuno standard per gli eventi di massa, siano essi update, delete o insert, che pertanto andrebbe discusso e valutato. Pertanto, questa opzione andrebbe __evitata__ fin quando possibile e fintanto che non si renda __strettamente necessario__!*

Se si ha a disposizione la lista degli id coinvolti dall'operazione di massa ed è noto uno specifico payload comune a tutti i record, si potrebbe trasmettere un evento specifico:

```php
$newValues = ['something' => Str::random(4)];

$utenti = ExampleModel::whereIn('id', $elencoId)->update($newValues);

foreach($elencoId as $id) {
    $elencoModels[] = [ExampleModel::class, $id];
}

app('storyteller')->happened(['massUpdated', $newValues], ...$elencoModels);
```

### Comportamento sotto transazione

Le operazioni di log sui dati vengono sempre intercettate in maniera indipendente dall'eventuale transazione: se successivamente al log venisse richiesto il rollback di una o più operazioni, i log associati ai models che hanno effettuato operazioni, sebbene queste siano state annullate, non sarebbero cancellati.

Per ovviare a questo inconveniente, è possibile "posticipare" temporaneamente il log delle operazioni di uno o più Models finché non ne viene esplicitamente invocata la scrittura (ad esempio a seguito del commit).

```php
// indico di posticipare il log per questo Model
app('storyteller')->pushDeferrable(ExampleModel::class);
```

```php
\DB::transaction(function() {
    // operazioni sotto transazione
    ...
    ...
    ...
});

// se arrivi fin qui, vuol dire che le operazioni qui sopra
// sono andate a buon fine, quindi puoi loggare
app('storyteller')->dispatchDeferred(ExampleModel::class);
```

Se si opta per una gestione esplicita della transazione:

```php
\DB::beginTransaction();

// operazioni sotto transazione
...
...
...

if($tuttoOk) {
    \DB::commit();
    app('storyteller')->dispatchDeferred(ExampleModel::class);
}

else {
    \DB::rollback();
    app('storyteller')->flushDeferred(ExampleModel::class);
}
```

Infine, va ripristinato il comportamento dei Model:

```php
app('storyteller')->resetAllDefers();
```








## Codici delle Exceptions

Le Exceptions restituiscono uno status pari al valore 1000 + lo status HTTP equivalente al tipo di errore, per facilitarne l'identificazione.

Oltre a questo, esistono i seguenti codici:

- 1000: l'istanza o una delle sue dipendenze deve essere settata o inizializzata per procedere
- 1001: l'istanza è già inizializzata o settata, quindi non è possibile ripetere l'operazione su questa istanza






