<?php

namespace Shellrent\OpenBanking\Models;

use stdClass;

class PaymentStatus extends GenericPaymentInfo {
	/**
	 * Hydrates specific data
	 * @param stdClass $data
	 */
	protected function hydrateData( stdClass $data ) {
		parent::hydrateData( $data->payload );
	}
}
