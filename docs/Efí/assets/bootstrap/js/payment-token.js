const identificadorDeConta = '95a2fa9ebbf664a98eeb2a0c3aecb901';
const sandbox = true;
const rotaBase = sandbox ? 'https://cobrancas-h.api.efipay.com.br' : 'https://cobrancas.api.efipay.com.br';

var s = document.createElement('script');
s.type = 'text/javascript';
var v = parseInt(Math.random() * 1000000);
s.src = `${rotaBase}/v1/cdn/${identificadorDeConta}/${v}`;
s.async = false;
s.id = identificadorDeConta;
if (!document.getElementById(identificadorDeConta)) {
    document.getElementsByTagName('head')[0].appendChild(s);
};
$gn = {
    validForm: true,
    processed: false,
    done: {},
    ready: function (fn) {
        $gn.done = fn;
    }
};