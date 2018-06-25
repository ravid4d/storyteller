<?php

namespace AmcLab\Storyteller;

use Traversable;
use ReflectionClass;
use App\Packages\RemoteLog\Drivers\RemoteLogDriverInterface;

class RemoteLogService {

    protected $driver;

    /**
     * Crea un'istanza del logger remoto
     *
     * @param string $providerName nome del provider per il quale istanziare il driver
     * @param string $destinationName nome del contenitore di dati di destinazione (es tabella mysql o collection di mongodb)
     */
    public function __construct(string $providerName, string $destinationName){

        // costruisce, a partire dal nome del provider, il path completo della classe da istanziare
        $driverClassName = __NAMESPACE__ . '\\Drivers\\' . studly_case($providerName . '_driver');

        // verifica che questa classe implementi la RemoteLogDriverInterface
        $interfaces = (new ReflectionClass($driverClassName))->getInterfaces();
        if (!array_key_exists(RemoteLogDriverInterface::class, $interfaces)) {
            throw new \Exception("$driverClassName must implement " . RemoteLogDriverInterface::class);
        }

        $this->driver = new $driverClassName($providerName, $destinationName);
    }

    /**
     * Restituisce l'istanza del driver creata
     *
     * @return mixed
     */
    public function getDriver() {

    }

    /**
     * Aggiunge una riga di log relativo al record dell'entità $entity, identificato da $key,
     * contenente le informazioni in $data
     *
     * @param string $entity
     * @param mixed $key
     * @param array $data
     * @return boolean
     */
    public function push(string $entity, $key, array $data) : bool {
        $this->driver->push($entity, $key, $data);
        return true;
    }

    /**
     * Restituisce l'elenco di righe di log per l'entità di tipo $entity con
     * identificativo $key
     *
     * @param string $entity
     * @param mixed $key
     * @return Traversable
     */
    public function list(string $entity, $key) : Traversable {
        return $this->driver->list($entity, $key);
    }

    /**
     * Cancella il log relativo al record di tipo $entity con id $key
     *
     * @param string $entity
     * @param mixed $key
     * @return boolean
     */
    public function purge(string $entity, $key) : bool {
        return $this->driver->purge($entity, $key);
    }

}
