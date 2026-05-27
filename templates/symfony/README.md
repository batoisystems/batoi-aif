# Symfony Starter

Copy the files from `examples/symfony/` into a Symfony app:

- `config/packages/batoi_aif.yaml` into Symfony config
- `config/services.yaml` entries into your services config
- `AifFactory.php` and `SymfonyExecutionContextResolver.php` into `src/Aif`
- `AifController.php` into `src/Controller`

The example keeps all AI calls behind `AifApi` and `AifGateway`.

