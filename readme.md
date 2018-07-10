# AmcLab\Storyteller

Auditor e logger eventi per Laravel.

***...Work in progress...***

## Todo

- Gestione job falliti (è presente solo una bozza del metodo fail(), ma manca l'intera gestione, comprese le migrations delle tabelle)
- Receiver per mysql (più relative migrations!!!)
- possibilità di selezionare quali eventi loggare (sia eloquent che auth)
- formalizzare (anche se supportati parzialmente) gli eventi relativi alle operazioni di massa
- creare coda di eventi non loggati ma da loggare dopo il commit

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

### Log operazioni per User (Authenticatable)

Il log degli eventi generati dai Model che implementano l'interfaccia ```Illuminate\Contracts\Auth\Authenticatable``` avviene in maniera automatica, ma soltanto per gli eventi ```Login```, ```Logout``` e ```PasswordReset```.

### Log di altre operazioni

Potrebbe essere necessario, in qualche occasione, legare un evento personalizzato e tracciare questo sul log operazioni del Model. Ad esempio: viene richiesta la stampa di un elenco di nominativi e va tracciato l'evento ("printed") comprensivo di data e ora corrente.

```php
$lista = Anagrafica::get();
stampa($lista);

function stampa($lista) {
    ...
    ...
    app('storyteller')->happened($evento, ...$lista);
}
```

***Nota: il log di altre operazioni non prevede che sia automaticamente propagato agli eventuali Model collegati!***






### Esempio











## Limitazioni e possibili workaround

L'attuale versione di Storyteller non è autonomamente in grado di:

- loggare operazioni di massa invocate mediante il QueryBuilder
- gestire l'eventuale rollback dei log nel caso di operazioni sotto transazione

### Log delle operazioni di massa

Le operazioni di update e cancellazione di massa, ossia quelle che vengono invocate mediante il QueryBuilder, [non possono essere loggate](https://laravel.com/docs/5.6/eloquent#updates) poiché, non passando da Eloquent, non lanciano un evento.

Prendendo come esempio la seguente richiesta:

```php
ExampleModel::where([['id', '<', 300]])->update(['something' => str_random(4)]);
```

L'operazione di update in oggetto non verrebbe tracciata da Storyteller perché il metodo update() viene eseguito non su un'istanza di Model, bensì su un'istanza di QueryBuilder e per questo motivo non è possibile intercettare le singole operazioni e/o i singoli record coinvolti.

Per ovviare a questo limite esistono due workaround, di seguito illustrati.

#### Iterare singolarmente i Models coinvolti

Usando il metodo get() o - meglio ancora - il metodo cursor() del QueryBuilder, è possibile lanciare le operazioni in sequenza iterando ad uno ad uno i Model coinvolti dal costrutto where:

```php
$utenti = ExampleModel::where([['id', '<', 300]]);

foreach($utenti->cursor() as $utente) {
    $utente->update(['something' => str_random(4)]);
}
```

*Nota: l'operazione è (ovviamente) molto meno performante dell'equivalente operazione lato QueryBuilder!*

#### Lanciare un equivalente evento personalizzato

*Nota: al momento non è ancora stato formalizzato alcuno standard per gli eventi di massa, siano essi update, delete o insert, che pertanto andrebbe discusso e valutato. Pertanto, questa opzione andrebbe __evitata__ fin quando possibile e fintanto che non si renda __strettamente necessario__!*

Se si ha a disposizione la lista degli id coinvolti dall'operazione di massa ed è noto uno specifico payload comune a tutti i record, si potrebbe trasmettere un evento specifico:

```php
$newValues = ['something' => str_random(4)];

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






