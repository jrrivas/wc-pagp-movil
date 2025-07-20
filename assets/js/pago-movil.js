jQuery(document).ready(function ($) {
    $('form.checkout').on('checkout_place_order', function () {
        if ($('#payment_method_pago_movil:checked').length === 0) return true;

        let isValid = true;

        // Teléfono
        const telefono = $('input[name="pago_movil_telefono"]').val();
        if (!/^[0-9]{11}$/.test(telefono)) {
            alert("Por favor ingresa un número de teléfono válido (11 dígitos).");
            isValid = false;
        }

        // Referencia
        const referencia = $('input[name="pago_movil_referencia"]').val().trim();
        if (referencia === "") {
            alert("Por favor ingresa el número de referencia.");
            isValid = false;
        }

        // Fecha
        const fecha = $('input[name="pago_movil_fecha"]').val();
        if (fecha === "") {
            alert("Por favor selecciona la fecha del pago.");
            isValid = false;
        }

        // Comprobante
        const inputFile = $('input[name="pago_movil_comprobante"]')[0];
        if (inputFile.files.length === 0) {
            alert("Por favor sube el comprobante del pago.");
            isValid = false;
        } else {
            const file = inputFile.files[0];
            const allowedTypes = PagoMovilVars.allowedTypes;
            const maxSize = PagoMovilVars.maxSize;

            if (file.size > maxSize) {
                alert(PagoMovilVars.errorSize);
                isValid = false;
            } else if (!allowedTypes.includes(file.type)) {
                alert(PagoMovilVars.errorFormat);
                isValid = false;
            }
        }

        return isValid;
    });
});