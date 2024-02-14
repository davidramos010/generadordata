// Función para generar un NIF español aleatorio
function generarNIF() {
    var numero = Math.floor(Math.random() * 1000000000);
    var letra = 'TRWAGMYFPDXBNJZSQVHLCKE'[numero % 23]; // Cálculo del dígito de control
    return numero.toString() + letra;
}

function generarNIE() {
    // Selección aleatoria de la letra inicial (X, Y, o Z)
    var letraInicial = ['X', 'Y', 'Z'][Math.floor(Math.random() * 3)];

    // Generar un número aleatorio de 7 dígitos
    var numero = Math.floor(Math.random() * 10000000);

    // Calcular el dígito de control
    var letras = 'TRWAGMYFPDXBNJZSQVHLCKE';
    var letraControl = letras[numero % 23];

    return letraInicial + numero.toString() + letraControl;
}

function generarCIF() {
    // Letra inicial del CIF
    const letras = 'ABCDEFGHJKLMNPQRSUVW';
    const letraInicial = letras.charAt(Math.floor(Math.random() * letras.length));

    // Generar números aleatorios (este es un ejemplo simple, no sigue las reglas exactas de generación de CIF)
    const numeros = [];
    for (let i = 0; i < 7; i++) {
        numeros.push(Math.floor(Math.random() * 10));
    }

    // Calcular el dígito de control
    const letrasControl = 'JABCDEFGHI';
    const cadena = letraInicial + numeros.join('');
    let suma = 0;
    for (let i = 0; i < cadena.length; i++) {
        let digito = parseInt(cadena.charAt(i), 10);
        if ((i + 1) % 2 === 0) {
            suma += digito;
        } else {
            let doble = digito * 2;
            suma += (doble < 10) ? doble : doble - 9;
        }
    }
    let digitoControl = letrasControl.charAt(10 - (suma % 10));

    // Construir el CIF completo
    const cif = letraInicial + numeros.join('') + digitoControl;
    return cif;
}

function copiNif(){
    var input = $('#hddCopiNif')[0];
    input.select();
    document.execCommand("copy");
    $("#alertCopiItem").fadeIn().delay(2000).fadeOut();
    setTimeout(function() {
        $("#alertCopiItem").hide(300,'');
    }, 3000);
}

function copiNie(){
    var input = $('#hddCopiNie')[0];
    input.select();
    document.execCommand("copy");
    $("#alertCopiItem").fadeIn().delay(2000).fadeOut();
    setTimeout(function() {
        $("#alertCopiItem").hide(300,'');
    }, 3000);
}

function copiCif(){
    var input = $('#hddCopiCif')[0];
    input.select();
    document.execCommand("copy");
    $("#alertCopiItem").fadeIn().delay(2000).fadeOut();
    setTimeout(function() {
        $("#alertCopiItem").hide(300,'');
    }, 3000);
}

$(document).ready(function() {

    $("#alerta .close").click(function() {
        $("#alerta").hide();
    });

    $('#divGenerateNif').click(function() {
        let nif = generarNIF();
        $('#divCopiNif').text(" " + nif);
        $('#hddCopiNif').val(nif);
        let nuevoElemento = $('<li> - '+nif+'</li>');
        $('#ulHistorialNif').prepend(nuevoElemento);
    });

    $('#divCopiNif').click(function() {
        let nif = copiNif();
    });

    //-------------------------------
    $('#divGenerateNie').click(function() {
        let nie = generarNIE();
        $('#divCopiNie').text(" " + nie);
        $('#hddCopiNie').val(nie);
        let nuevoElemento = $('<li> - '+nie+'</li>');
        $('#ulHistorialNie').prepend(nuevoElemento);
    });

    $('#divCopiNie').click(function() {
        let nif = copiNie();
    });

    //-------------------------------
    $('#divGenerateCif').click(function() {
        let cif = generarCIF();
        $('#divCopiCif').text(" " + cif);
        $('#hddCopiCif').val(cif);
        let nuevoElemento = $('<li> - '+cif+'</li>');
        $('#ulHistorialCif').prepend(nuevoElemento);
    });

    $('#divCopiCif').click(function() {
        let nif = copiNie();
    });

});
