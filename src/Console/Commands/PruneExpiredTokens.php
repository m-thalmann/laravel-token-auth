<?php

namespace TokenAuth\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Builder;
use TokenAuth\TokenAuth;

class PruneExpiredTokens extends Command {
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'tokenAuth:prune-expired
                            {type : The type of token to prune}
                            {--hours=24 : The number of hours to retain expired or revoked tokens}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Prune tokens expired or revoked for more than specified number of hours.';

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle() {
        $type = $this->argument('type');

        if (
            !in_array($type, [TokenAuth::TYPE_ACCESS, TokenAuth::TYPE_REFRESH])
        ) {
            $this->warn("There exists no token type '$type'");

            return Command::INVALID;
        }

        $model = TokenAuth::$authTokenModel;
        $hours = $this->option('hours');

        $removeBefore = now()->subHours($hours);

        $model
            ::where('type', $type)
            ->where(function (Builder $query) use ($removeBefore) {
                $query->where('expires_at', '<=', $removeBefore);
                $query->orWhere('revoked_at', '<=', $removeBefore);
            })
            ->delete();

        $this->info(
            "Tokens expired/revoked for more than {$hours} hours pruned successfully."
        );

        return Command::SUCCESS;
    }
}
