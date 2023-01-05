<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use App\Repository\Connection\ConnectionRepository;

class ConnectionRequest extends FormRequest
{
    private $connection;

    public function __construct(ConnectionRepository $connection)
    {
        parent::__construct();
        $this->connection = $connection;
    }

    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize()
    {
        return true;
    }

    /**
     * Prepare the data for validation.
     *
     * @return void
     */
    protected function prepareForValidation()
    {
        if (empty($this->date_range)) {
            $dateFilterStart = $dateFilterEnd = $this->connection->dateFilter();

            $this->merge([
                'month_day_range' => "{$dateFilterStart->format('Y-m-d')} to {$dateFilterEnd->format('Y-m-d')}",
            ]);
        } else {
            $dateFilterStart = $this->date_range[0];
            $dateFilterEnd = isset($this->date_range[1]) ? $this->date_range[1] : $this->date_range[0];

            $this->merge([
                'month_day_range' => "{$dateFilterStart} to {$dateFilterEnd}",
            ]);
        }
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules()
    {
        return [
            //
        ];
    }
}
