package nl.delaparra_services.apps.eupay.ui.viewmodel;

import dagger.internal.DaggerGenerated;
import dagger.internal.Factory;
import dagger.internal.Provider;
import dagger.internal.QualifierMetadata;
import dagger.internal.ScopeMetadata;
import javax.annotation.processing.Generated;
import nl.delaparra_services.apps.eupay.service.P2PService;

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
public final class SendViewModel_Factory implements Factory<SendViewModel> {
  private final Provider<P2PService> p2pServiceProvider;

  public SendViewModel_Factory(Provider<P2PService> p2pServiceProvider) {
    this.p2pServiceProvider = p2pServiceProvider;
  }

  @Override
  public SendViewModel get() {
    return newInstance(p2pServiceProvider.get());
  }

  public static SendViewModel_Factory create(Provider<P2PService> p2pServiceProvider) {
    return new SendViewModel_Factory(p2pServiceProvider);
  }

  public static SendViewModel newInstance(P2PService p2pService) {
    return new SendViewModel(p2pService);
  }
}
