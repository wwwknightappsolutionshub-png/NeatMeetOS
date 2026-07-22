<?php

namespace App\Shared\Commerce\Contracts;

use App\Domains\Booking\Models\Appointment;
use App\Shared\Commerce\DTO\AppointmentCheckoutImportDto;

interface CheckoutImportFromBookingContract
{
    public function import(Appointment $appointment): AppointmentCheckoutImportDto;
}
