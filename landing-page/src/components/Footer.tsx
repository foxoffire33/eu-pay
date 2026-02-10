import { Box, Container, Group, Text, Anchor, Divider, Stack } from '@mantine/core';

export function Footer() {
  return (
    <Box component="footer" py="xl" bg="gray.0">
      <Container size="lg">
        <Divider mb="lg" />
        <Group justify="space-between" align="flex-start">
          <Stack gap="xs">
            <Text fw={700}>Stichting EU Pay</Text>
            <Text size="sm" c="dimmed">
              Dutch foundation (KVK) for European payment sovereignty
            </Text>
            <Text size="sm" c="dimmed">
              Licensed under EUPL-1.2
            </Text>
          </Stack>
          <Group gap="xl">
            <Stack gap="xs">
              <Text size="sm" fw={600}>Project</Text>
              <Anchor href="https://github.com/user/eu-pay" size="sm" c="dimmed">
                Source Code
              </Anchor>
              <Anchor href="https://github.com/user/eu-pay/releases" size="sm" c="dimmed">
                Releases
              </Anchor>
            </Stack>
            <Stack gap="xs">
              <Text size="sm" fw={600}>Legal</Text>
              <Anchor href="/privacy" size="sm" c="dimmed">
                Privacy Policy
              </Anchor>
              <Anchor href="/imprint" size="sm" c="dimmed">
                Imprint
              </Anchor>
            </Stack>
            <Stack gap="xs">
              <Text size="sm" fw={600}>Contact</Text>
              <Anchor href="mailto:info@eu-pay.nl" size="sm" c="dimmed">
                info@eu-pay.nl
              </Anchor>
            </Stack>
          </Group>
        </Group>
      </Container>
    </Box>
  );
}
