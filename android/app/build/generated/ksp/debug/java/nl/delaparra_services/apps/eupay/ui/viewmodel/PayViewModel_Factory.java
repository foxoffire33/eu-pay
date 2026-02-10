package nl.delaparra_services.apps.eupay.ui.viewmodel;

import android.app.Application;
import dagger.internal.DaggerGenerated;
import dagger.internal.Factory;
import dagger.internal.Provider;
import dagger.internal.QualifierMetadata;
import dagger.internal.ScopeMetadata;
import javax.annotation.processing.Generated;
import nl.delaparra_services.apps.eupay.service.CardService;
import nl.delaparra_services.apps.eupay.service.PaymentService;

@ScopeMetadata
@QualifierMetadata
@DaggerGenerated
@Generated(
    value = "dagger.internal.codegen.ComponentProcessor",
    comments = "https://dagger.dev"
)
@SuppressWarnings({
    "unchecked",
    "rawtypes",
    "KotlinInternal",
    "KotlinInternalInJava",
    "cast",
    "deprecation",
    "nullness:initialization.field.uninitialized"
})
public final class PayViewModel_Factory implements Factory<PayViewModel> {
  private final Provider<PaymentService> paymentServiceProvider;

  private final Provider<CardService> cardServiceProvider;

  private final Provider<Application> applicationProvider;

  public PayViewModel_Factory(Provider<PaymentService> paymentServiceProvider,
      Provider<CardService> cardServiceProvider, Provider<Application> applicationProvider) {
    this.paymentServiceProvider = paymentServiceProvider;
    this.cardServiceProvider = cardServiceProvider;
    this.applicationProvider = applicationProvider;
  }

  @Override
  public PayViewModel get() {
    return newInstance(paymentServiceProvider.get(), cardServiceProvider.get(), applicationProvider.get());
  }

  public static PayViewModel_Factory create(Provider<PaymentService> paymentServiceProvider,
      Provider<CardService> cardServiceProvider, Provider<Application> applicationProvider) {
    return new PayViewModel_Factory(paymentServiceProvider, cardServiceProvider, applicationProvider);
  }

  public static PayViewModel newInstance(PaymentService paymentService, CardService cardService,
      Application application) {
    return new PayViewModel(paymentService, cardService, application);
  }
}
