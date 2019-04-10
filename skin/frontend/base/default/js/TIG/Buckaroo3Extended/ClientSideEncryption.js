function getEncryptedData() {
    var cardNumber = jQuery_1123("#cardNumber").val();
    var cvc = jQuery_1123("#cvc").val();
    var cardHolderName = jQuery_1123("#cardHolderName").val();
    var expirationYear = jQuery_1123("#expirationYear").val();
    var expirationMonth = jQuery_1123("#expirationMonth").val();
    var cardNumberValid = BuckarooClientSideEncryption.V001.validateCardNumber(cardNumber);
    var cvcValid = BuckarooClientSideEncryption.V001.validateCvc(cvc);
    var cardHolderNameValid = BuckarooClientSideEncryption.V001.validateCardholderName(cardHolderName);
    var expirationYearValid = BuckarooClientSideEncryption.V001.validateYear(expirationYear);
    var expirationMonthValid = BuckarooClientSideEncryption.V001.validateMonth(expirationMonth);
    debugger;
    if (!cardNumberValid || !cvcValid || !cardHolderNameValid || !expirationYearValid || !expirationMonthValid) {
        return;
    }
    console.log('2');
    BuckarooClientSideEncryption.V001.encryptCardData(cardNumber,
        expirationYear,
        expirationMonth,
        cvc,
        cardHolderName,
        function (encryptedCardData) {
            jQuery_1123("#buckaroo3extended_creditcard_encryptedCardData").val(encryptedCardData)
        });
    console.log('3');
}

function checkCardType(cardNumberElement) {
    var matches = {
        'American Express': {
            'regex': '^(34|37)[0-9]{13}$',
            'logo': ''
        },
        'Bankcard': {
            'regex': '^5610[0-9]{12}|(56022[1-5][0-9]{10})$',
            'logo': ''
        },
        'China T-Union': {
            'regex': '^31[0-9]{17}$',
            'logo': ''
        },
        'China UnionPay': {
            'regex': '^62[0-9]{14,17}$',
            'logo': ''
        },
        'Diners Club enRoute': {
            'regex': '^(2014|2149)[0-9]{11}$',
            'logo': ''
        },
        'Diners Club International': {
            'regex': '^(3095|30[0-5][0-9]|38[0-9]{2}|36[0-9]{2}|39[0-9]{2})[0-9]{10,15}$',
            'logo': ''
        },
        'Diners Club US & Canada': {
            'regex': '^(54|55)[0-9]{14}$',
            'logo': ''
        },
        'Discover': {
            'regex': '^(6011[0-9]{2}|(622(12[6-9]|[2-8][0-9]{2}|9[0-5][0-9])|62[4-6][0-9]{3}|628[2-8][0-9]{2})|64[0-9]{4}|65[0-9]{4})[0-9]{10,13}$',
            'logo': ''
        },
        'Rupay': {
            'regex': '^(60[0-9]{2}|6521|6522)[0-9]{12}$',
            'logo': ''
        },
        'InterPayment': {
            'regex': '^636[0-9]{13,16}$',
            'logo': ''
        },
        'InstaPayment': {
            'regex': '^63[7-9][0-9]{13}$',
            'logo': ''
        },
        'JCB': {
            'regex': '^(2131|1800|3528|3529|35[3-8])[0-9]{12,15}$',
            'logo': ''
        },
        'Laser': {
            'regex': '^(6304|6706|6771|6709)[0-9]{12,15}$',
            'logo': ''
        },
        'Maestro UK': {
            'regex': '^(6759[0-9]{2}|676770|676774)[0-9]{6,13}$',
            'logo': ''
        },
        'Dankort': {
            'regex': '^(5019|4571)[0-9]{12}$',
            'logo': ''
        },
        'MIR': {
            'regex': '^220[0-4][0-9]{12}$',
            'logo': ''
        },
        'Mastercard': {
            'regex': '^(222[1-9]|2[3-6][0-9]{2}|27[0-1][0-9]|2720|5[1-5][0-9]{2})[0-9]{12}$',
            'logo': ''
        },
        'Solo': {
            'regex': '^(6334|6767)[0-9]{12,15}$',
            'logo': ''
        },
        'Switch': {
            'regex': '^(4903[0-9]{2}|4905[0-9]{2}|4911[0-9]{2}|4936[0-2]{2}|564182|633110|6333[0-9]{2}|6759[0-9]{2})[0-9]{10,13}$',
            'logo': ''
        },
        'Troy': {
            'regex': '^9792[0-8][0-9]{11}$',
            'logo': ''
        },
        'Visa': {
            'regex': '^4[0-9]{15,18}$',
            'logo': ''
        },
        'UATP': {
            'regex': '^1[0-9]{14}$',
            'logo': ''
        },
        'Verve': {
            'regex': '^(506099|5061[0-8][0-8]|50619[0-8]|65000[2-9]|65001[0-9]|65002[0-7])[0-9]{10}([0-9]{3})?$',
            'logo': ''
        }
    };

    var creditcardNumber = cardNumberElement.value;
    for (var key in matches) {
        if (creditcardNumber.match(matches[key].regex)) {
                jQuery_1123("#buckaroo3extended_creditcard_method").val(key)

        }
    }
}
