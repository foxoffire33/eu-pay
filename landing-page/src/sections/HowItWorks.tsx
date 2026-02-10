import {
  Container,
  Title,
  Text,
  Timeline,
  ThemeIcon,
  Stack,
} from '@mantine/core';
import {
  IconDownload,
  IconLink,
  IconCash,
  IconDeviceMobile,
} from '@tabler/icons-react';

const steps = [
  {
    icon: IconDownload,
    title: 'Download',
    description: 'Get EU Pay from GitHub Releases or F-Droid. Free and open source.',
  },
  {
    icon: IconLink,
    title: 'Link Your Bank',
    description:
      'Connect via PSD2 Open Banking — standardized, secure, and EU-regulated. 140+ banks supported.',
  },
  {
    icon: IconCash,
    title: 'Top Up',
    description:
      'Add funds via iDEAL, SEPA, or any EU bank. Money arrives in your EU Pay account.',
  },
  {
    icon: IconDeviceMobile,
    title: 'Tap to Pay',
    description:
      'Hold your phone to any contactless terminal. Done. Works at every store that accepts Visa.',
  },
];

export function HowItWorks() {
  return (
    <Container size="sm" py={80}>
      <Stack align="center" gap="lg" mb={48}>
        <Title order={2} ta="center">
          How It Works
        </Title>
        <Text size="lg" c="dimmed" ta="center">
          From download to your first payment in minutes.
        </Text>
      </Stack>
      <Timeline active={-1} bulletSize={40} lineWidth={2}>
        {steps.map((step) => (
          <Timeline.Item
            key={step.title}
            bullet={
              <ThemeIcon size={40} radius="xl" variant="filled">
                <step.icon size={20} />
              </ThemeIcon>
            }
            title={
              <Text fw={600} size="lg">
                {step.title}
              </Text>
            }
          >
            <Text size="sm" c="dimmed" mt={4}>
              {step.description}
            </Text>
          </Timeline.Item>
        ))}
      </Timeline>
    </Container>
  );
}
