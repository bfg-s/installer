<?phpnamespace Bfg\Installer\Providers;use Bfg\Installer\Processor\DumpAutoloadProcessor;use Bfg\Installer\Processor\InstallProcessor;use Bfg\Installer\Processor\UnInstallProcessor;use Bfg\Installer\Processor\UpdateProcessor;use Illuminate\Support\ServiceProvider;use JetBrains\PhpStorm\Pure;/** * Class InstalledProvider * @package Bfg\Installer\Providers */abstract class InstalledProvider extends ServiceProvider{    /**     * The version of extension.     * @var string     */    public string $version = '0.0.1';    /**     * The name of extension.     * @var string|null     */    public ?string $name = null;    /**     * The child type for sub     * extensions of extension.     * @var string|null     */    public ?string $child = null;    /**     * The type to determine who     * owns the extension.     * @var string|null     */    public ?string $type = 'bfg-app';    /**     * The description of extension.     * @var string|null     */    public ?string $description = null;    /**     * The logo of extension.     * @var string|null     */    public ?string $logo = null;    /**     * InstalledProvider constructor.     * @param mixed|\Illuminate\Contracts\Foundation\Application $app     */    public function __construct($app)    {        parent::__construct($app);        $app->bind(static::class, fn () => $this);    }    /**     * Executed when the provider is registered     * and the extension is installed.     * @return void     */    abstract function installed(): void;    /**     * Executed when the provider run method     * "boot" and the extension is installed.     * @return void     */    abstract function run(): void;    /**     * Executed when the parent provider is     * registered and the extension is installed.     * @param  InstalledProvider  $provider     * @return static     */    public function installed_parent(InstalledProvider $provider): static    {        return $this;    }    /**     * Executed when the parent provider run method     * "boot" and the extension is installed.     * @param  InstalledProvider  $provider     * @return static     */    public function run_parent(InstalledProvider $provider): static    {        return $this;    }    /**     * Register route settings     * @return void     * @throws \ReflectionException     */    public function register()    {        \Installer::registrationPackage(static::class, [            'name' => $this->name ?? static::class,            'version' => $this->version,            'child' => $this->child,            'type' => $this->type,            'description' => $this->description,            'provider' => static::class,        ]);        if (\Installer::isInstalledPackage(static::class)) {            $this->installed();            foreach (\Installer::getPackage(static::class, 'extensions', []) as $item) {                app($item)->installed_parent($this);            }        }    }    /**     * Bootstrap services.     * @return void     */    public function boot()    {        if (\Installer::isInstalledPackage(static::class)) {            $this->run();            foreach (\Installer::getPackage(static::class, 'extensions', []) as $item) {                app($item)->run_parent($this);            }        }    }    /**     * Run on install extension.     * @param  InstallProcessor  $processor     * @return $this     */    public function install(InstallProcessor $processor): static    {        $processor->command->line("Installation of " . ($this->name ? ucfirst($this->name) : static::class) . '...');        return $this;    }    /**     * Run on update extension.     * @param  UpdateProcessor  $processor     * @return $this     */    public function update(UpdateProcessor $processor): static    {        $processor->command->line("Updating of " . ($this->name ? ucfirst($this->name) : static::class) . '...');        return $this;    }    /**     * Run on uninstall extension.     * @param  UnInstallProcessor  $processor     * @return $this     */    public function uninstall(UnInstallProcessor $processor): static    {        $processor->command->line("Uninstalling of " . ($this->name ? ucfirst($this->name) : static::class) . '...');        return $this;    }    /**     * Run on dump extension.     * @param  DumpAutoloadProcessor  $processor     * @return $this     */    public function dump(DumpAutoloadProcessor $processor): static    {        $processor->command->info("> Dumping of " . ($this->name ? ucfirst($this->name) : static::class) . '...');        return $this;    }}