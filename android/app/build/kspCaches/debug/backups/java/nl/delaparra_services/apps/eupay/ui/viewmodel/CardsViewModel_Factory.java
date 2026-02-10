package nl.delaparra_services.apps.eupay.ui.viewmodel;

import dagger.internal.DaggerGenerated;
import dagger.internal.Factory;
import dagger.internal.Provider;
import dagger.internal.QualifierMetadata;
import dagger.internal.ScopeMetadata;
import javax.annotation.processing.Generated;
import nl.delaparra_services.apps.eupay.service.CardService;

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
public final class CardsViewModel_Factory implements Factory<CardsViewModel> {
  private final Provider<CardService> cardServiceProvider;

  public CardsViewModel_Factory(Provider<CardService> cardServiceProvider) {
    this.cardServiceProvider = cardServiceProvider;
  }

  @Override
  public CardsViewModel get() {
    return newInstance(cardServiceProvider.get());
  }

  public static CardsViewModel_Factory create(Provider<CardService> cardServiceProvider) {
    return new CardsViewModel_Factory(cardServiceProvider);
  }

  public static CardsViewModel newInstance(CardService cardService) {
    return new CardsViewModel(cardService);
  }
}
