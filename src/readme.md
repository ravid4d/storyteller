# AmcLab\Storyteller

Auditor e logger eventi per Laravel.

...Work in progress...

## Todo

- Gestione job falliti (è presente solo una bozza del metodo fail(), ma manca l'intera gestione, comprese le migrations delle tabelle)
- Receiver per mysql (più relative migrations!!!)
- possibilità di selezionare quali eventi loggare (sia eloquent che auth)
- formalizzare (anche se supportati parzialmente) gli eventi relativi alle operazioni di massa
- creare coda di eventi non loggati ma da loggare dopo il commit

## Requisiti

- ...tutto lo stack AmcLab

## Installazione

## A che serve?

Questo package serve a tenere traccia, su un apposito store, di tutti gli eventi che coinvolgono le entità di database e gli utenti di applicazione.

Sarà possibile tracciare:
- Tutte le operazioni di creazione, modifica, cancellazione ed eventuale restore dei record, se si usa SoftDeletes;
- Tutte le operazioni di accesso effettuate dagli utenti;

Questo permette di ottenere, mediante una semplice API:
- L'elenco di tutte le modifiche effettuate da uno specifico operatore;
- L'elenco dettagliato di tutte le operazioni che hanno coinvolto uno specifico record.

## Limitazioni

L'attuale versione di Storyteller non è autonomamente in grado di:

- loggare operazioni di massa invocate mediante il QueryBuilder
- gestire l'eventuale rollback dei log nel caso di operazioni sotto transazione

Alcuni possibili workaround per bypassare queste limitazioni sono indicati più avanti, nel relativo paragrafo.

## Documentazione

Per abilitare il log degli eventi di Eloquent, è necessario che il Model usi il trait TellableTrait.

```php
namespace App\Models;

use AmcLab\Storyteller\Traits\TellableTrait;

class ExampleModel
{
    use TellableTrait;

    ...
```

Il log degli eventi generati dall'Autenticathor di Laravel avviene in maniera automatica, ma soltanto per gli eventi Login, Logout e PasswordReset e per i Model che implementano l'interfaccia ```Illuminate\Contracts\Auth\Authenticatable```.




Il log avviene determinando qual è il database di riferimento mediante Environment e scrivendo nell'opportuno Receiver di riferimento.

Il Receiver può puntare a qualsiasi "collettore" di dati che implementi l'interfaccia Contracts\Receiver.

### Esempio















## Workaround alle limitazioni

### Log delle operazioni di massa

Le operazioni di update e cancellazione di massa, ossia quelle che vengono invocate mediante il QueryBuilder, [non possono essere loggate](https://laravel.com/docs/5.6/eloquent#updates) poiché, non passando da Eloquent, non lanciano un evento.

Prendendo come esempio la seguente richiesta:

```php
ExampleModel::where([['id', '<', 300]])->update(['something' => str_random(4)]);
```

L'operazione di update in oggetto non verrebbe tracciata dallo storyteller perché il metodo update() viene eseguito non su un'istanza di Model, bensì su un'istanza di QueryBuilder e per questo motivo non è possibile intercettare le singole operazioni e/o i singoli record coinvolti.

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

Se si ha a disposizione la lista degli id coinvolti dall'operazione di massa ed è noto uno specifico payload comune a tutti i record, si potrebbe trasmettere un evento specifico (***attenzione, vedi nota***):

```php
$newValues = ['something' => str_random(4)];

$utenti = ExampleModel::whereIn('id', $elencoId)->update($newValues);

foreach($elencoId as $id) {
    $elencoModels[] = [ExampleModel::class, $id];
}

app('storyteller')->happened(['massUpdated', $newValues], ...$elencoModels);
```

*Nota: al momento non è ancora stato formalizzato alcuno standard per gli eventi di massa, siano essi update, delete o insert, che pertanto andrebbe discusso e valutato. Pertanto, attualmente andrebbe __evitata__ fin quando possibile e fintanto che non si renda __strettamente necessario__!*

### Comportamento sotto transazione

Le operazioni di log sui dati vengono sempre trasmesse allo Storyteller in maniera indipendente dall'eventuale transazione: se successivamente al log venisse richiesto il rollback di una o più operazioni, i log associati ai models che hanno effettuato operazioni, sebbene queste siano state annullate, non sarebbero cancellati.

Per ovviare a questo inconveniente, è possibile "posticipare" temporaneamente il log delle operazioni di uno o più Models finché non ne viene esplicitamente invocata la scrittura (ad esempio a seguito del commit).

```php
// indico di posticipare il log per questo Model
app('storyteller')->pushDeferrable(ExampleModel::class);
```

Dopodiché va inserito un comando per l'invocazione del log a seguito del commit:

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






