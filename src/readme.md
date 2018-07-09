# AmcLab\Storyteller

Auditor e logger eventi per Laravel.

...Work in progress...

## Todo

- Gestione job falliti (è presente solo una bozza del metodo fail(), ma manca l'intera gestione, comprese le migrations delle tabelle)

- Receiver per mysql (più relative migrations!!!)

- possibilità di selezionare quali eventi loggare (sia eloquent che auth)

## Requisiti

- ...tutto lo stack AmcLab

## Installazione

## Documentazione

Per abilitare il log degli eventi di eloquent, è necessario che il Model usi il trait TellableTrait.

Il log degli eventi generati dall'autenticathor di Laravel avviene in maniera automatica, ma soltanto per gli eventi Login, Logout e PasswordReset).

Il log avviene determinando qual è il database di riferimento mediante Environment e scrivendo nell'opportuno Receiver di riferimento.

Il Receiver può puntare a qualsiasi "collettore" di dati che implementi l'interfaccia Contracts\Receiver.

#### TellableTrait

...







## Note importanti

Le eccezioni restituiscono uno status simile agli status HTTP, per facilitarne l'identificazione (1000 + status equivalente).

Altrimenti:

- 1000: la dipendenza di riferimento o una delle dipendenze richieste deve essere istanziata per procedere
- 1001: la dipendenza è già stata istanziata, quindi non può essere riistanziata






