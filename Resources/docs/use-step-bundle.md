Configure this bundle for StepBundle
------------------------------------

## Introduction

This bundle can work with [IDCIStepBundle](https://github.com/IDCI-Consulting/StepBundle)

## Installation

Make sure you have already installed step bundle

Add this to your ```config.yml``` file:

```yaml
imports:
    - {resource: '@IDCIPaymentBundle/Resources/config/step_types.yml'}
    - {resource: '@IDCIPaymentBundle/Resources/config/event_actions.yml'}
    - {resource: '@IDCIPaymentBundle/Resources/config/step_templates.yml'}
```

And this in your ```services.yml``` file:
```yaml
# PaymentBundle - Step

IDCI\Bundle\PaymentBundle\Step\Type\PaymentStepType:
    tags:
        - { name: idci_step.step_type, alias: payment }
IDCI\Bundle\PaymentBundle\Step\Type\PaymentStepTypeInterface: '@IDCI\Bundle\PaymentBundle\Step\Type\PaymentStepType'

# PaymentBundle - Step Event Action

IDCI\Bundle\PaymentBundle\Step\Event\Action\ManageTransactionStepEventAction:
    arguments:
        $templates: '%idci_payment.templates.step%'
    tags:
        - {name: idci_step.step_event_action, alias: manage_transaction}
```

## Override default twig views

if you want to override default step twig template, add this to your configuration:

```yaml
idci_payment:
    templates:
        step:
            approved: '@IDCIPaymentBundle/Resources/views/PaymentStep/approved.html.twig'
            canceled: '@IDCIPaymentBundle/Resources/views/PaymentStep/canceled.html.twig'
            created: '@IDCIPaymentBundle/Resources/views/PaymentStep/created.html.twig'
            failed: '@IDCIPaymentBundle/Resources/views/PaymentStep/failed.html.twig'
            pending: '@IDCIPaymentBundle/Resources/views/PaymentStep/pending.html.twig'
```

You can now modify which views this bundle will use in case of payment step

## Example of payment step

```json
{
    ...,
    "payment": {
        "type": "payment",
        "options": {
            "title": "title.payment",
            "events": {
                "form.pre_set_data": [
                    {
                        "action": "manage_transaction",
                        "parameters": {
                            "allow_skip": "{{ app.request.session.get('allow_skip_payment_step')|default('0') }}",
                            "payment_gateway_configuration_alias": "paypal_test",
                            "amount": "{{ flow_data.retrievedData.order.amount * 100 }}",
                            "currency_code": "EUR",
                            "item_id": "{{ flow_data.retrievedData.order.id }}",
                            "description": "A transaction test",
                            "customer_id": "{{ flow_data.retrievedData.user.id }}",
                            "customer_email": "{{ flow_data.data.user.email_address }}",
                            "success_message": "Your transaction succeeded.",
                            "error_message": "There was a problem with your transaction, please try again."
                        }
                    }
                ]
            }
        }
    }
}
```

Info: on user return a transaction will be set in the retrieved data if you want to use it in next steps.
